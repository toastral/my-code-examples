<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * promo code processing
 */
class promocode extends MX_Controller
{
    public $module;
    public $tb_promocode;

    public function __construct()
    {
        parent::__construct();
        $this->load->model(get_class($this) . '_model', 'model');
        //Config Module
        $this->tb_promocode = PROMOCODE;
        $this->module = get_class($this);
        $this->columns = [
            "code" => 'Code',
            "title" => "Title",
            "Discount" => "Discount",
            "created" => "Created",
            "expired" => "Expired",
            "used" => "Used",
            "is_active" => "Status"
        ];
    }

    /**
     * index
     * @return void
     */
    public function index()
    {
        $page = (int)get("p");
        $page = ($page > 0) ? ($page - 1) : 0;
        $limit_per_page = get_option("default_limit_per_page", 10);
        $query = array();
        $query_string = "";
        if (!empty($query)) {
            $query_string = "?" . http_build_query($query);
        }
        $config = array(
            'base_url' => cn(get_class($this) . $query_string),
            'total_rows' => $this->model->get_count_items(),
            'per_page' => $limit_per_page,
            'use_page_numbers' => true,
            'prev_link' => '<i class="fe fe-chevron-left"></i>',
            'first_link' => '<i class="fe fe-chevrons-left"></i>',
            'next_link' => '<i class="fe fe-chevron-right"></i>',
            'last_link' => '<i class="fe fe-chevrons-right"></i>',
        );
        $this->pagination->initialize($config);
        $links = $this->pagination->create_links();
        $items = $this->model->get_items($limit_per_page, $page * $limit_per_page);
        $this->load->model("order/order_model", "order_model1");
        $sum_promo = $this->order_model1->get_sum_promo();

        $data = array(
            "module" => get_class($this),
            "items" => $items,
            "pagination" => $links,
            "columns" => $this->columns,
            "sum_promo" => $sum_promo
        );
        $this->template->build('index', $data);
    }

    /**
     * show add promo template
     * @return void
     */
    public function add()
    {
        $data = array(
            "module" => get_class($this),
        );
        $this->template->build('add', $data);
    }

    /**
     * Check promo
     * @param $title string
     * @param $code string
     * @param $expired string
     * @param $percent integer
     * @return array|string[]
     */
    private function validate_add($title, $code, $expired, $percent)
    {
        if (empty($expired)) {
            return ["status" => "error", "message" => "Choose expired date", "value" => $expired];
        }
        if (!preg_match("|^[\d]{4}\-[\d]{2}\-[\d]{2}$|", $expired)) {
            return ["status" => "error", "message" => "Invalid date format", "value" => $expired];
        }
        $percent = abs((int)$percent);
        if ($percent > 100 || $percent <= 0) {
            return ["status" => "error", "message" => "Invalid percent value"];
        }
        $code = strtoupper($code);
        $res = $this->model->validate_code2($code);
        if ($res["status"] == "error") {
            return $res;
        }
        $row = $this->model->get_row_by_code($code);
        if (!empty($row)) {
            return ["status" => "error", "message" => "This code has been there before. Try to come up with another one."];
        }

        return [
            "title" => $title,
            "code" => $code,
            "expired" => $expired,
            "percent" => $percent,
            "status" => "success"
        ];
    }

    /**
     * add promo
     * @return json
     */
    public function ajax_add()
    {
        $res = $this->validate_add(post("title"), post("code"), post("expired"), post("percent"));

        if ($res["status"] == "error") {
            ms($res);
        }

        $res = $this->db->insert($this->tb_promocode, [
            "code" => $res["code"],
            "title" => $res["title"],
            "discount_coeff" => $res["percent"] / 100,
            "expired" => $res["expired"],
            "created" => date("Y-m-d", time()),
        ]);

        ms([
            "status" => "success",
            "message" => lang("Update_successfully")
        ]);
    }

    /**
     * validate promo
     * @return json
     */
    public function ajax_check()
    {
        $total = post("total");

        if (!preg_match("/^\\$[\d\.]+$/", $total)) {
            ms([
                "status" => "error",
                "message" => "Invalid param total: " . $total
            ]);
        }
        $total = ltrim($total, "$");
        $total = (float)$total;

        $promocode = post("promocode");

        $res = $this->model->validate_code2($promocode);
        if ($res["status"] == "error") {
            ms([
                "status" => "error",
                "message" => "Invalid code"
            ]);
        }

        $row = $this->model->get_row_by_code($promocode, 1);
        if (empty($row)) {
            ms([
                "status" => "error",
                "message" => "Invalid code"
            ]);
        }

        ms([
            "status" => "success",
            "total" => "$" . sprintf('%01.2f', $total * (1 - $row->discount_coeff))
        ]);
    }

}

