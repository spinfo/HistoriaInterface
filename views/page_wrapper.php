
<style>
    <!--
        .shtm_message {
            margin: 3px;
            padding: 3px;
            width: 80%;
        }

        .shtm_message_success {
            background-color: rgba(92, 184, 92, 0.4);
        }

        .shtm_message_info {
            background-color: rgba(91, 192, 222, 0.4);
        }

        .shtm_message_warning {
            background-color: rgba(240, 173, 78, 0.4);
        }

        .shtm_message_error {
            background-color: rgba(217, 83, 79, 0.4);
        }
    -->
</style>


<div id="shtm_container" style="margin:2%; padding:2%;">

    <div id="shtm_messages">

        <?php foreach($this->message_service->messages as $msg): ?>

            <div class="shtm_message shtm_message_<?php echo $msg->get_label() ?>">

                <b><?php echo $msg->get_prefix() ?>: </b><?php echo $msg->text ?>

            </div>

        <?php endforeach ?>

    </div>

    <div id="shtm_content">

        <?php $this->content->render() ?>

    </div>
</div>

