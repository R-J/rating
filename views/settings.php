<?php defined('APPLICATION') or die ?>
<h1><?= $this->data('Title') ?></h1>
<div class="Description">This plugin allows users to rate discussions and comments and empowers the admin to sort posts based on the rating.<br/></div>
<div class="FormWrapper">
<?= $this->Form->open(), $this->Form->errors() ?>
<ul>
    <li>
        <?= $this->Form->label('Comment Sort Field', 'SortField') ?>
        <?= $this->Form->radioList('SortField', $this->data('SortField')) ?>
    </li>
    <li>
        <?= $this->Form->label('Sort Direction', 'SortDirection') ?>
        <?= $this->Form->radioList('SortDirection', $this->data('SortDirection')) ?>
    </li>
    <li>
        <?= $this->Form->label('Set "Top" as Homepage', 'TopHome') ?>
        <?= $this->Form->checkBox('TopHome') ?>
    </li>
    <li>
        <?= $this->Form->label('Default time period to show') ?>
        <?= $this->Form->dropDown('Period', $this->data('Period')) ?>
    </li>
</ul>
<?= $this->Form->close('Save'); ?>
</div>
