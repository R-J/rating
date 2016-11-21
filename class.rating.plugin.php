<?php

$PluginInfo['rating'] = [
    'Name' => 'Rating',
    'Description' => 'Allows users to up- or down-vote discussions and comments. <div class="Warning">Don\'t use this plugin together with Yaga!</div>',
    'Version' => '0.1',
    'RequiredApplications' => ['Vanilla' => '2.2'],
    'RequiredTheme' => false,
    'RequiredPlugins' => false,
    'SettingsUrl' => 'settings/rating',
    'SettingsPermission' => 'Garden.Settings.Manage',
    'HasLocale' => false,
    'RegisterPermissions' => [
        'Plugins.Rating.Add',
        'Plugins.Rating.Manage'
    ],
    'Author' => 'Robin Jurinka',
    'AuthorUrl' => 'http://vanillaforums.org/profile/r_j',
    'License' => 'MIT'
];


// error_reporting(E_ALL);
// ini_set("display_errors", 1);


// TODO: make links point to signin for guests
// Don't change sort order for all views!
// Instead create new menu entry "Recent"|"Top"|...
// Sort comments by rank is a admin config setting
// Option to make "Top" the default page
class RatingPlugin extends Gdn_Plugin {
    /**
     * Init db changes.
     *
     * @return void.
     */
    public function setup() {
        $this->structure();
    }

    /**
     * Transform Discussion/Comment Score to indexed integer column.
     *
     * This would make a better performance but might be destructive!
     *
     * @return void.
     */
    public function structure(){
        // Replace null with zero in Score
        Gdn::structure()->table('Discussion')
            ->column('Score', 'int(11)', 0, 'index')
            ->set();
        Gdn::structure()->table('Comment')
            ->column('Score', 'int(11)', 0, 'index')
            ->set();
    }

    /**
     * Allows enabling plugin and changing sort order.
     *
     * @param SettingsController $sender Instance of the calling class.
     *
     * @return void.
     */
    public function settingsController_rating_create($sender) {
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

    public function base_afterDiscussionFilters_handler($sender) {
        $cssClass = 'Top';
        if (strtolower(Gdn::controller()->ControllerName) == 'discussionscontroller' && strtolower(Gdn::controller()->RequestMethod) == 'top') {
            $cssClass .= ' Active';
        }
        ?>
        <li class="<?= $cssClass ?>">
            <?= anchor(sprite('SpTop').t('Top'), '/discussions/top') ?>
        </li>
        <?php
    }

    /**
     * Optionally add CSS & JS files. Do plugins permission checks.
     *
     * Permission is checked once per controller here so that it doesn't need
     * to be done on every event fired.
     *
     * @param GardenController $sender Instance of the calling class.
     *
     * @return void.
     */
    public function base_render_before($sender) {
        // Include style & script.
        $sender->addCssFile('rating.css', 'plugins/rating');
        $sender->addJsFile('rating.js', 'plugins/rating');

        // Check for adding permissions only once.
        if (
            Gdn::session()->checkPermission(
                ['Plugins.Rating.Add', 'Plugins.Rating.Manage'],
                false
            )
        ) {
            // Reflect permission in css class.
            $cssClass = ' RatingAllowed';
            $sender->addDefinition('RatingPermission', true);
        } else {
            $cssClass = ' RatingDisallowed';
        }
        $sender->CssClass .= $cssClass;
    }

    public function printRatingTemplate($postType, $postID, $score) {
        ?>
        <div class="RatingContainer Rating<?= $postType ?>" data-posttype="<?= $postType ?>" data-postid="<?= $postID ?>">
            <a class="RatingUp"><?= t('&#x25B2;') ?></a>
            <span class="Rating" data-rating="<?= $score ?>"><?= $score ?></span>
            <a class="RatingDown"><?= t('&#x25BC;') ?></a>
        </div>
        <?php
    }

    /**
     * Insert rating markup into discussions lists.
     *
     * @param GardenController $sender Instance of the calling class.
     * @param mixed            $args   Event arguments.
     *
     * @return void.
     */
    public function base_beforeDiscussionContent_handler($sender, $args) {
        // Adding voting controls destroys table layout.
        if ($sender->View == 'table') {
            return;
        }
        $this->printRatingTemplate(
            'Discussion',
            $args['Discussion']->DiscussionID,
            $args['Discussion']->Score
        );
    }

    /**
     * Insert rating markup into discussion.
     *
     * @param GardenController $sender Instance of the calling class.
     * @param mixed            $args   Event arguments.
     *
     * @return void.
     */
    public function base_beforeDiscussionDisplay_handler($sender, $args) {
        $this->printRatingTemplate(
            'Discussion',
            $args['Discussion']->DiscussionID,
            $args['Discussion']->Score
        );
    }

    /**
     * Insert rating markup into comment.
     *
     * @param GardenController $sender Instance of the calling class.
     * @param mixed            $args   Event arguments.
     *
     * @return void.
     */
    public function base_beforeCommentMeta_handler($sender, $args) {
        $this->printRatingTemplate(
            'Comment',
            $args['Comment']->CommentID,
            $args['Comment']->Score
        );
    }

    /**
     * Endpoint for changing a discussion/comments rating.
     *
     * Only called from js files. Needs parameters
     * type: "discussion" (default) or "comment"
     * id:   The DiscussionID or CommentID
     * rate: "up" (default) or "down"
     * "Plugins.Rating.Add" or "Plugins.Rating.Manage" permissions needed.
     * @param PluginController $sender Instance of the calling class.
     * @param mixed            $args   Event arguments.
     *
     * @return bool Whether Score has been updated.
     */
    public function pluginController_rating_create($sender, $args) {
       // Check for valid TransientKey to prevent clickbaiting.
        if (!Gdn::session()->validateTransientKey(Gdn::request()->get('tk', false))) {
            throw new InvalidArgumentException('TransientKey invalid.');
        }
        // Sanity check for post id.
        $postID = intval(Gdn::request()->get('id', 0));
        if ($postID <= 0) {
            throw new InvalidArgumentException('PostID invalid.');
        }
        // Get post type
        if (Gdn::request()->get('type', 'Discussion') == 'Comment') {
            $postType = 'Comment';
        } else {
            $postType = 'Discussion';
        }
        $modelName = $postType.'Model';
        $postModel = new $modelName();

        // Prevent users from voting on their own posts.
        if (!Gdn::session()->checkPermission('Plugins.Rating.Manage')) {
            $post = $postModel->getID($postID);
            if ($post->InsertUserID == Gdn::session()->UserID) {
                return false;
            }
        }

        // Determine rating.
        if (Gdn::request()->get('rate', 'up') == 'down') {
            $score = -1;
        } else {
            $score = 1;
        }

        $currentScore = $postModel->getUserScore(
            $postID,
            Gdn::session()->UserID
        );

        $newScore = $currentScore + $score;
        if (
            !Gdn::session()->checkPermission('Plugins.Rating.Manage') &&
            ($newScore > 1 || $newScore < -1)
        ) {
            // Score cannot exceed -1/1 for normal users. No change is made.
            return false;
        }
        // Echoes the new total score of the post.
        echo $postModel->setUserScore(
            $postID,
            Gdn::session()->UserID,
            $newScore
        );

        // Return the score to allow js to update the rating.
        return true;
    }

    public function discussionsController_top_create($sender) {
        $sender->title('Top Rated');
        $sender->index(val(0, $sender->RequestArgs, ''));
    }

    /**
     * Change comments sort order.
     *
     * There is no config setting for this, so that must be set dynamically.
     *
     * @param CommentModel $sender Instance of the calling class.
     *
     * @return void.
     */
    public function commentModel_afterConstruct_handler($sender) {
        if (c('Vanilla.Discussions.SortField', '') == 'Score') {
            $sender->orderBy(
                c('rating.Comments.SortField', 'Score').
                ' '.
                c('Vanilla.Discussions.SortDirection', 'desc')
            );
        }
    }
}
