<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * bulk mailing
 */
class massmail extends MX_Controller
{
    public $module;
    public $tb_mass_mail;
    public $tb_mass_mail_list;

    public function __construct()
    {
        parent::__construct();
        $this->load->model(get_class($this) . '_model', 'model');
        $this->load->model("massmail_list_model", 'massmail_list');
        //Config Module
        $this->tb_mass_mail = MASS_MAIL;
        $this->tb_mass_mail_list = MASS_MAIL_LIST;
        $this->module = get_class($this);

        $this->columns = [
            "id" => 'Id',
            "status" => "Status",
            "title" => "Title",
            "start" => "start",
            "end" => "End",
            "total_emails" => "Total",
            "sent_emails" => "Sent"
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
            'total_rows' => $this->model->get_count_items($this->model->history_statuses),
            'per_page' => $limit_per_page,
            'use_page_numbers' => true,
            'prev_link' => '<i class="fe fe-chevron-left"></i>',
            'first_link' => '<i class="fe fe-chevrons-left"></i>',
            'next_link' => '<i class="fe fe-chevron-right"></i>',
            'last_link' => '<i class="fe fe-chevrons-right"></i>',
        );

        $this->pagination->initialize($config);
        $links = $this->pagination->create_links();
        $items = $this->model->get_items($limit_per_page, $page * $limit_per_page, $this->model->history_statuses);


        $active_item = $this->model->get_active_item();
        $stats = $this->massmail_list->get_stats();

        $data = array(
            "module" => get_class($this),
            "items" => $items,
            "active_item" => $active_item,
            "pagination" => $links,
            "columns" => $this->columns,
            "stats" => $stats,
            "btn_disable_logic" => $this->model->get_btn_disable_logic($active_item),
            "progress" => $this->massmail_list->get_progress($stats),
        );
        $this->template->build('index', $data);
    }

    /**
     * edit mail
     * @param $id integer
     * @return void
     */
    public function edit($id = null)
    {
        $row = null;
        if ($id) {
            $row = $this->model->get_item_by_id($id);
            if (!$row) {
                ms(["status" => "error", "message" => "Bad mail id"]);
            }
        }
        $data = [
            "module" => get_class($this),
            "row" => $row,
        ];
        $this->template->build('update', $data);
    }

    /**
     * check and return
     * @param $title string
     * @param $message string
     * @param $interval_sec integer
     * @return array
     */
    private function validate_are_empty($title, $message, $interval_sec)
    {

        if (empty($title)) {
            return ["status" => "error", "message" => "Fill title field", "value" => $title];
        }

        if (empty($message)) {
            return ["status" => "error", "message" => "Fill message field", "value" => $message];
        }

        $interval_sec = abs((int)$interval_sec);
        if (empty($interval_sec)) {
            return ["status" => "error", "message" => "Fill interval_sec field", "value" => $interval_sec];
        }

        return [
            "title" => $title,
            "message" => $message,
            "interval_sec" => $interval_sec,
            "status" => "success",
        ];
    }

    /**
     * edit mail list
     * @return json
     */
    public function ajax_edit()
    {
        $res = $this->validate_are_empty(post("title"), $this->input->post("message"), post("interval_sec"));

        if ($res["status"] == "error") {
            ms($res);
        }

        $id = abs(intval(post("id")));
        if (empty($id)) {
            // проверить, что записей нет  с new и paused
            if ($this->model->get_count_items(['new', 'pause', 'progress'])) {
                ms([
                    "status" => "error",
                    "message" => "Cannot be added because the current status is 'new', 'progress' or 'pause'. Wait for the broadcast to complete or force it to end before adding a new message."
                ]);
            }
            $res = $this->db->insert($this->tb_mass_mail, [
                "title" => $res["title"],
                "message" => $res["message"],
                "interval_sec" => $res["interval_sec"],
            ]);

        } else {
            $this->db->where("id", $id);
            $this->db->update($this->tb_mass_mail, [
                "title" => $res["title"],
                "message" => $res["message"],
                "interval_sec" => $res["interval_sec"],
            ]);
        }

        ms([
            "status" => "success",
            "message" => lang("Update_successfully")
        ]);
    }

    /**
     * duplicate email
     * @param $id integer
     * @return json
     */
    public function ajax_duplicate_item($id)
    {
        // надо проверить есть ли активные мейлы, если есть - ошибка
        if ($this->model->get_count_items(['new', 'pause', 'progress'])) {
            ms([
                "status" => "error",
                "message" => "Cannot be duplicated because the current status is 'new', 'progress' or 'pause'. Wait for the broadcast to complete or force it to end before adding a new message."
            ]);
        }

        $row = $this->model->get_item_by_id($id);

        if (!$row) {
            ms([
                "status" => "error",
                "message" => "Bad mail id"
            ]);
        }

        $res = $this->db->insert($this->tb_mass_mail, [
            "title" => $row->title,
            "message" => $row->message,
            "interval_sec" => $row->interval_sec,
        ]);

        ms([
            "status" => "success",
            "message" => lang("Duplicate_successfully")
        ]);
    }

    /**
     * set broadcast mode
     * @return json
     */
    public function ajax_click_cmd()
    {
        $click_id = post("click_id");

        if (!in_array($click_id, ["progress", "pause", "force_end"])) {
            ms([
                "status" => "error",
                "message" => "Not valid click id"
            ]);
        };
        $active_item = $this->model->get_active_item();
        if (empty($active_item)) {
            ms([
                "status" => "error",
                "message" => "Not found active mail"
            ]);
        }

        $stats = $this->massmail_list->get_stats();
        if ($stats->not_send <= 0 && $click_id == "progress") {
            ms([
                "status" => "error",
                "message" => "Not emails for sending. Add emails to not send queue"
            ]);
        }

        // здесь not_send > 0

        if ($click_id == "progress" && empty($active_item->start)) {
            $this->db->where("id", $active_item->id);
            $this->db->update($this->tb_mass_mail, ["start" => date("Y-m-d H:i:s")]);
        }

        if ($click_id == "force_end") {
            $this->db->update($this->tb_mass_mail_list, ["is_send" => 1]);
        }

        $this->db->where("id", $active_item->id);
        $this->db->update($this->tb_mass_mail, ["status" => $click_id]);

        ms([
            "status" => "success",
            "message" => lang("Update_successfully")
        ]);
    }

    /**
     * reactivation mailing
     * @return json
     */
    public function ajax_click_reactivate()
    {
        $stats = $this->massmail_list->get_stats();
        if ($stats->total < 0) {
            ms([
                "status" => "error",
                "message" => "Empty mail list"
            ]);
        }

        if ($stats->total != $stats->sent) {
            ms([
                "status" => "error",
                "message" => "Wait until broadcasting ended and than reactivate"
            ]);
        }

        $this->massmail_list->reactivate();

        ms([
            "status" => "success",
            "message" => "Reactivation completed successfully"
        ]);
    }

    /**
     * clear the queue
     * @return json
     */
    public function ajax_click_clear()
    {
        $this->massmail_list->clear();

        ms([
            "status" => "success",
            "message" => "Clear completed successfully"
        ]);
    }

    /**
     * show stats
     * @return json
     */
    public function ajax_progress_data()
    {
        $stats = $this->massmail_list->get_stats();
        ms([
            "status" => "success",
            "message" => "all_good",
            "data" => [
                "progress" => $this->massmail_list->get_progress($stats),
                "stats" => $stats
            ]
        ]);
    }

    /**
     * delete mail
     * @param $id
     * @return json
     */
    public function ajax_delete_item($id)
    {
        $this->db->delete($this->tb_mass_mail, ["id" => $id]);
        ms(array(
            "status" => "success",
            "ids" => $id,
            "message" => lang("Deleted_successfully")
        ));
    }
}

