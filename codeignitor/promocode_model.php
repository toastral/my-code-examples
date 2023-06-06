<?php
defined('BASEPATH') or exit('No direct script access allowed');

class promocode_model extends MY_Model
{
    public $tb_promocode;

    public function __construct()
    {
        parent::__construct();
        $this->tb_promocode = PROMOCODE;
    }

    /**
     * get promos
     * @param $limit string|integer
     * @param $start string|integer
     * @return mixed
     */
    function get_items($limit = "", $start = "")
    {
        $this->db->select('*');
        $this->db->from($this->tb_promocode);
        $this->db->order_by('id', 'DESC');
        $this->db->limit($limit, $start);
        $query = $this->db->get();
        $result = $query->result();
        return $result;
    }

    /**
     * count promos
     * @return mixed
     */
    public function get_count_items()
    {
        $this->db->select('pc.id');
        $this->db->from($this->tb_promocode . " pc");
        $query = $this->db->get();
        return $query->num_rows();
    }

    /**
     * get promo row
     * @param $code string
     * @param $is_active null|integer
     * @return false
     */
    public function get_row_by_code($code, $is_active = null)
    {
        $this->db->select('*');
        $this->db->from($this->tb_promocode);
        $this->db->where('code', $code);
        if (!is_null($is_active)) {
            $this->db->where('is_active', $is_active);
        }
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            return $query->row();
        }
        return false;
    }

    /**
     * validate promo
     * @param $code string
     * @return string[]
     */
    public function validate_code2($code)
    {
        if (strlen($code) <= 4) {
            return ["status" => "error", "message" => "The code is too short. Try to come up with more then 4 letters."];
        }
        if (!preg_match("/[A-Z0-9-]{4,}/", $code)) {
            return ["status" => "error", "message" => "In code allowed only english letters, digits and dashs"];
        }

        return ["status" => "success", "message" => ""];
    }

    /**
     * get promo coef
     * @param $code string
     * @return int
     */
    public function get_k_by_code($code)
    {
        $row = $this->get_row_by_code($code);
        if (!$row) return 0;
        return $row->discount_coeff;
    }

    /**
     * get id n coef by promo
     * @param $promocode string
     * @return array
     */
    public function get_promo_id_n_coeff($promocode)
    {
        $discount_coeff = 0;
        $promocode_id = 0;
        if ($promocode) {
            $promocode_row = $this->get_row_by_code($promocode);
            if ($promocode_row) {
                $discount_coeff = $promocode_row->discount_coeff;
                $promocode_id = $promocode_row->id;
            }
        }
        return [$promocode_id, $discount_coeff];
    }

}
