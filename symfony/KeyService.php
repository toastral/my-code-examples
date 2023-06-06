<?php

namespace App\Service;

use App\Entity\Key;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

class KeyService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @param array $ids
     * @return array
     */
    public function getKeysById(array $ids): array
    {
        $keys = $this->entityManager->getRepository(Key::class)->findBy(['id' => $ids]);

        if ($keys === null) {
            throw new InvalidArgumentException('Invalid key ids provided');
        }

        return array_map(static fn(Key $key) => $key->getWord(), $keys);
    }


    /**
     * @param array $ids
     * @return array
     */
    public function getIdKeysByIds(array $ids): array // @TODO test
    {
        $keys = $this->entityManager->getRepository(Key::class)->findBy(['id' => $ids]);

        if ($keys === null) {
            throw new InvalidArgumentException('Invalid key ids provided');
        }

        $result = [];
        foreach ($keys as $key) {
            $result[$key->getId()] = $key->getWord();
        }

        return $result;
    }

    /**
     * @param array $words
     * @return array
     */
    public function getIdByKeys(array $words): array
    {
        $keys = $this->entityManager->getRepository(Key::class)->findBy(['word' => $words]);

        if ($keys === null) {
            throw new InvalidArgumentException('Invalid key words provided');
        }

        return array_map(static fn(Key $key) => $key->getId(), $keys);
    }

    /**
     * @param array $phrases
     * @param string $delimiter
     * @return string
     */
    public function makeQuery(array $phrases, string $delimiter = ' '): string
    {
        if ($delimiter !== ' ') $delimiter = " " . $delimiter . " ";
        return implode($delimiter, $phrases);
    }

    /**
     * @param string $query
     * @param string $delimiter
     * @return array
     */
    public function chunkQuery(string $query, string $delimiter = ' '): array
    {
        // Заменяем кавычки на уникальные разделители
        $quoteReplaced = str_replace(['"', "'"], ['{{quote}}', '{{quote}}'], $query);

        // Заменяем разделители на уникальный разделитель
        $delimReplaced = str_replace($delimiter, '{{delimiter}}', $quoteReplaced);

        // Заменяем уникальные разделители внутри кавычек на пробелы
        $preChunked = preg_replace_callback('/{{quote}}(.*?){{quote}}/s', function ($matches) {
            return str_replace('{{delimiter}}', ' ', $matches[0]);
        }, $delimReplaced);

        // Разбиваем строку на массив
        $chunked = explode('{{delimiter}}', $preChunked);

        // Удаляем кавычки и пробелы в начале и конце каждой фразы
        $chunked = array_map(static fn(string $phrase) => trim(str_replace('{{quote}}', '', $phrase)), $chunked);

        return $chunked;
    }

    /**
     * @param string $query
     * @param string $delimiter
     * @return string
     */
    public function normalizeQuery(string $query, string $delimiter = ' '): string
    {
        $phrases = $this->chunkQuery($query, $delimiter);

        // преобразуем фразы в id
        $ids = $this->getIdByKeys($phrases);

        // сортируем id
        sort($ids);

        // преобразуем отсортированные id обратно в фразы
        $sortedPhrases = $this->getKeysById($ids);

        // преобразуем отсортированные фразы обратно в строку, добавляя кавычки вокруг каждой фразы
        $normalizedQuery = implode(' ', array_map(function ($phrase) {
            return "\"$phrase\"";
        }, $sortedPhrases));

        return $normalizedQuery;
    }

    /**
     * @param array $ids
     * @param string $delimiter
     * @return string
     */
    public function makeQueryByKeyId(array $ids, string $delimiter = ' '): string
    {
        $phrases = $this->getKeysById($ids);
        $phrases = array_map(function ($phrase) {
            return "\"$phrase\"";
        }, $phrases);

        return implode($delimiter, $phrases);
    }

}
