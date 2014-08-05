<?php

echo '<h2>' . get_admin_page_title() . '</h2>';
$this->list_table->prepare_items();
$this->list_table->display();