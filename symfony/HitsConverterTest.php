<?php

namespace App\Tests;

use App\Entity\Key;
use App\Service\HitsConverter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class HitsConverterTest extends KernelTestCase
{
    private $em;
    private $converter;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();

        $this->em = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();

        $this->converter = new HitsConverter($this->em);

        $this->truncateTable();

        $this->keyId0 = $this->createKey('фраза ноль')->getId();
        $this->keyId1 = $this->createKey('фраза 1')->getId();
        $this->keyId2 = $this->createKey('фраза 2')->getId();
        $this->keyId3 = $this->createKey('фраза 3')->getId();
        $this->keyId4 = $this->createKey('фраза нагрузка')->getId();
    }

    protected function truncateTable()
    {
        $connection = $this->em->getConnection();
        $platform = $connection->getDatabasePlatform();
        $connection->executeUpdate($platform->getTruncateTableSQL('key', true /* whether to cascade */));
    }

    protected function createKey(string $word): Key
    {
        $key = new Key();
        $key->setWord($word);

        $this->em->persist($key);
        $this->em->flush();

        return $key;
    }

    public function _testSumHits()
    {
        $hits1 = $this->getHits1();
        $expectedHits2 = $this->getHits2();
        $result = $this->converter->sumHits($hits1);
        $this->assertEquals($expectedHits2, $result);
    }

    public function testFillZeroHits()
    {
        $hits2 = $this->getHits2();
        $words = ["фраза 1", "фраза ноль", "фраза 3", "фраза 2", "ferferferf"];
        $expectedHits3 = $this->getHits3();
        $result = $this->converter->fillZeroHits($hits2, $words);
        $this->assertEquals($expectedHits3, $result);
    }

    public function testSumAndFillZeroHits()
    {
        $words = ["фраза 1", "фраза ноль", "фраза 3", "фраза 2", "ferferferf"];
        $result = $this->converter->sumAndFillZeroHits($this->getHits1(), $words);
        $expectedHits3 = $this->getHits3();
        $this->assertEquals($expectedHits3, $result);
    }

    public function getHits1()
    {
        return
            [
                [
                    "id" => 1,
                    "start" => "719.73",
                    "dur" => "5.039",
                    "token" => "фраза 2",
                    "token_len" => 17
                ],
                [
                    "id" => 0,
                    "start" => "26.64",
                    "dur" => "4.41",
                    "token" => "фраза 1",
                    "token_len" => 16
                ],
                [
                    "id" => 1,
                    "start" => "719.73",
                    "dur" => "5.039",
                    "token" => "фраза 2",
                    "token_len" => 17
                ],
                [
                    "id" => 0,
                    "start" => "26.64",
                    "dur" => "4.41",
                    "token" => "фраза 3",
                    "token_len" => 16
                ]
            ];
    }

    public function getHits2()
    {
        return
            [
                [
                    "order" => 1,
                    "key_id" => "{$this->keyId2}",
                    "key" => "фраза 2",
                    "count" => 2
                ],
                [
                    "order" => 2,
                    "key_id" => "{$this->keyId1}",
                    "key" => "фраза 1",
                    "count" => 1
                ],
                [
                    "order" => 3,
                    "key_id" => "{$this->keyId3}",
                    "key" => "фраза 3",
                    "count" => 1
                ]
            ];
    }

    public function getHits3()
    {
        return
            [
                [
                    "order" => 1,
                    "key_id" => "{$this->keyId2}",
                    "key" => "фраза 2",
                    "count" => 2
                ],
                [
                    "order" => 2,
                    "key_id" => "{$this->keyId1}",
                    "key" => "фраза 1",
                    "count" => 1
                ],
                [
                    "order" => 3,
                    "key_id" => "{$this->keyId3}",
                    "key" => "фраза 3",
                    "count" => 1
                ],
                [
                    "order" => 4,
                    "key_id" => "{$this->keyId0}",
                    "key" => "фраза ноль",
                    "count" => 0
                ]
            ];
    }
}


