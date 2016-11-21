<?php

class Settings extends RJ_Plugin {
    /**
     * Needed to kickstart the meta class.
     *
     * @param string $pluginName The index of the plugin in the plugins array.
     *
     * @return  void.
     */
    public function __construct($pluginName) {
        parent::__construct($pluginName);
    }

    public function setup() {
        return true;
    }

    public function structure() {
        // Replace null with zero in Score
        Gdn::structure()->table('Discussion')
            ->column('Score', 'int(11)', 0, 'index')
            ->set();
        Gdn::structure()->table('Comment')
            ->column('Score', 'int(11)', 0, 'index')
            ->set();
    }

    public function index($sender) {
        $sender->permission('Garden.Settings.Manage');
        $sender->setData('Title', t('Rating Settings'));
        $sender->addSideMenu('dashboard/settings/plugins');

        $validation = new Gdn_Validation();
        $configurationModel = new Gdn_ConfigurationModel($validation);
        $configurationModel->setField(
            [
                'Vanilla.Discussions.SortDirection',
                'Vanilla.Discussions.SortField',
                'rating.Comments.SortField',
                'rating.RatingStep',
                'Plugins.rating.Enabled'
            ]
        );
        $sender->Form = new Gdn_Form();
        $sender->Form->setModel($configurationModel);
        // Choices for sort directions.
        $sender->setData(
            'SortDirection',
            ['desc' => 'Descending', 'asc' => 'Ascending']
        );
        // Choices for sort fields
        $sender->setData(
            'DiscussionSortField',
            [
                'Score' => 'Score',
                'DateInserted' => 'Date Created',
                'DateLastComment' => 'Date Last Active'
            ]
        );
        $sender->setData(
            'CommentSortField',
            [
                'Score' => 'Score',
                'DateInserted' => 'Date Created'
            ]
        );
        $sender->setData('YagaEnabled', Gdn::applicationManager()->isEnabled('Yaga'));

        if ($sender->Form->authenticatedPostBack() === false) {
            $sender->Form->setData($configurationModel->Data);
        } else {
            if ($sender->Form->save() !== false) {
                $sender->informMessage(t('Your settings have been saved.'));
            }
        }

        $sender->render($this->getView('settings.php'));
    }

    public function testecho($input = '') {
        echo $input;
    }
}
