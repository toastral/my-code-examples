<div class="page-header">
    <?php if (get_role("admin") || get_role("supporter")) {
        ?>
        <h1 class="page-title d-md-none">
            <?php echo "Mass Mail"; ?>
        </h1>
    <?php }
    ?>
</div>

<?php $this->view("header", ["btn_disable_logic" => $btn_disable_logic, 'active_item' => $active_item]); ?>

<div class="row" id="result_ajaxSearch">
    <?php if (!empty($items)) {
        ?>
        <div class="col-md-12 col-xl-12">

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Mails Log</h3>
                    <div class="card-options">
                        <a href="#" class="card-options-collapse" data-toggle="card-collapse"><i
                                    class="fe fe-chevron-up"></i></a>
                        <a href="#" class="card-options-remove" data-toggle="card-remove"><i class="fe fe-x"></i></a>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-bordered table-vcenter card-table">
                        <thead>
                        <tr>
                            <th class="text-center w-1"><?php echo lang("No_"); ?></th>
                            <?php if (!empty($columns)) {
                                foreach ($columns as $key => $row) {
                                    ?>
                                    <th><?php echo strip_tags($row); ?></th>
                                <?php }
                            } ?>

                            <?php
                            if (!get_role("user")) {
                                ?>
                                <th class="text-center"><?php echo lang('Action'); ?></th>
                            <?php } ?>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($items)) {
                            $i = 0;
                            foreach ($items as $key => $row) {
                                $i++;
                                ?>
                                <tr class="tr_<?= $row->id ?>">

                                    <td><?php echo strip_tags($i); ?></td>
                                    <td><?= $row->id; ?></td>
                                    <?php
                                    $badgeColor = "badge-warning";
                                    if ($row->status == "complete") {
                                        $badgeColor = "badge-info";
                                    }
                                    $popover = "";
                                    if ($row->status == "abort") {
                                        $popover = "data-container='body' data-trigger='hover' data-toggle='popover'  data-content='$row->description'";
                                    }
                                    ?>
                                    <td>
                                        <span class="badge <?= $badgeColor ?>" <?= $popover ?>><?= $row->status ?></span>
                                    </td>
                                    <?php
                                    $str = $row->message;
                                    $str = str_replace('"', "'", $str);
                                    ?>
                                    <td>
                                        <div class="title">
                                            <div data-placement="top" data-container="body" data-trigger="hover"
                                                 data-toggle="popover" data-content="<?= $str ?>">
                                                <span style="border-bottom: 1px dashed"><?php echo truncate_string(strip_tags($row->title), 30); ?></span>
                                            </div>
                                    </td>
                                    <td><?= $row->start ?></td>
                                    <td><?= $row->end ?></td>
                                    <td><?= $row->total_emails ?></td>
                                    <td><?= $row->sent_emails ?></td>
                                    <td class="text-center">
                                        <div class="btn-group">
                                            <a href="<?php echo cn("$module/ajax_duplicate_item/" . $row->id); ?>"
                                               class="btn btn-icon btn-outline-secondary ajaxDuplicateItem"
                                               data-toggle="tooltip" data-placement="bottom"
                                               title="<?php echo lang("Duplicate"); ?>"
                                               data-redirect="<?php echo get_current_url(); ?>"><i
                                                        class="fa fa-files-o"></i></a>
                                            <a href="<?php echo cn("$module/ajax_delete_item/" . $row->id); ?>"
                                               class="btn btn-icon btn-outline-danger ajaxDeleteItem"
                                               data-toggle="tooltip" data-placement="bottom"
                                               title="<?php echo lang("Delete"); ?>"><i class="fe fe-trash-2"></i></a>
                                        </div>
                                    </td>
                                </tr>
                            <?php }
                        } ?>

                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <!-- Get Pagination -->
        <div class="col-md-12">
            <div class="float-right">
                <?php echo $pagination; ?>
            </div>
        </div>
    <?php } else { ?>
        <?php echo Modules::run("blocks/empty_data"); ?>
    <?php } ?>
</div>


<script>
    $(function () {
        $(".copy").on("click", function () {
            navigator.clipboard.writeText($(this).data("code")).then(function () {
                console.log('Async: Copying to clipboard was successful!');
            }, function (err) {
                console.error('Async: Could not copy text: ', err);
            });
        })
    })

    // callback Delete item
    $(document).on("click", ".ajaxDuplicateItem", function (event) {
        event.preventDefault();
        var _that = $(this),
            _action = _that.attr("href"),
            _redirect = _that.data("redirect"),
            _data = $.param({token: token});
        $.post(_action, _data, function (_result) {
            pageOverlay.show();
            setTimeout(function () {
                pageOverlay.hide();
                notify(_result.message, _result.status);
            }, 2000);
            if (_result.status == 'success' && typeof _redirect != "undefined") {
                reloadPage(_redirect);
            }
        }, 'json')
    })

</script>