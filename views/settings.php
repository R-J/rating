<?php defined('APPLICATION') or die ?>
<h1><?= $this->data('Title') ?></h1>
<div class="Help Aside">Help Aside</div>
<div class="Description">This plugin allows users to rate discussions and comments and empowers the admin to sort posts based on the rating.<br/></div>

<div class="Warning">To keep this pluign simple, it "abuses" an existing column which will lead to problems if you have activated another plugin which makes use of that column! Do not use this plugin together with "Voting" or "YAGA"!</div>

<div class="Description">Technically spoken, the "Discussion" and "Comment" table column "Score" will be changed from type float to type integer, disallowing nulls and setting "0" as default value.<br/>
That will basically destroy all the information which is stored in the Score column</div>

<div class="FormWrapper">
<?= $this->Form->open(), $this->Form->errors() ?>

<ul>
    <li>
        <?= $this->Form->label('Use This Plugin Anyway!', 'Plugins.rating.Enabled') ?>
        <?= $this->Form->checkBox('Plugins.rating.Enabled', 'Enable plugin') ?>
    </li>
    <li>
        <?= $this->Form->label('Discussion Sort Field', 'Vanilla.Discussions.SortField') ?>
        <?= $this->Form->radioList('Vanilla.Discussions.SortField', $this->data('DiscussionSortField')) ?>
    </li>
    <li>
        <?= $this->Form->label('Comment Sort Field', 'rating.Comments.SortField') ?>
        <?= $this->Form->radioList('rating.Comments.SortField', $this->data('CommentSortField')) ?>
    </li>
    <li>
        <?= $this->Form->label('Sort Direction', 'Vanilla.Discussions.SortDirection') ?>
        <?= $this->Form->radioList('Vanilla.Discussions.SortDirection', $this->data('SortDirection')) ?>
    </li>
</ul>

<?= $this->Form->close('Save'); ?>
</div>