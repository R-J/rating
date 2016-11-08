<?php

$PluginInfo['rating'] = [
    'Name' => 'Rating',
    'Description' => 'Allows users to up- or down-vote discussions and comments.<div class="Warning">This plugin is not compatible with other plugins that use the Score column and you will loose information just by activating it!</div>',
    'Version' => '0.1',
    'RequiredApplications' => ['Vanilla' => '2.2'],
    'RequiredTheme' => false,
    'RequiredPlugins' => false,
    'SettingsUrl' => 'settings/rating',
    'SettingsPermission' => 'Garden.Settings.Manage',
    'HasLocale' => false,
    'RegisterPermissions' => [
        'Plugins.Rating.Add',
        'Plugins.Rating.View',
        'Plugins.Rating.Manage'
    ],
    'Author' => 'Robin Jurinka',
    'AuthorUrl' => 'http://vanillaforums.org/profile/r_j',
    'License' => 'MIT'
];


// TODO: disallow rating own posts
// TODO: change style
// TODO: make links point to signin for guests
class RatingPlugin extends Gdn_Plugin {
    /**
     * Init db changes.
     *
     * @return void.
     */
    public function setup() {
        // TODO: check for yaga and return
        // TODO: put this into settings
        saveToConfig('Vanilla.Discussions.SortField', 'Score');
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
        $sender->addCssFile('rating.css','plugins/rating');
        $sender->addJsFile('rating.js','plugins/rating');

        // Check for adding permissions only once.
        if (
            Gdn::session()->checkPermission(
                [
                    'Plugins.Rating.Add',
                    'Plugins.Rating.Manage'
                ],
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

    /**
     * Insert markup into discussions lists.
     *
     * @param GardenController $sender Instance of the calling class.
     * @param mixed            $args   Event arguments.
     *
     * @return void.
     */
    public function base_beforeDiscussionContent_handler($sender, $args) {
        ?>
        <div class="RatingContainer RatingDiscussion">
            <a class="RatingUp" DiscussionID="<?= $args['Discussion']->DiscussionID ?>"><?= t('&#x25B2;') ?></a>
            <span class="Rating"><?= intval($args['Discussion']->Score) ?></span>
            <a class="RatingDown" DiscussionID="<?= $args['Discussion']->DiscussionID ?>"><?= t('&#x25BC;') ?></a>
        </div>
        <div class="DiscussionContent">
        <?php
    }

    /**
     * Close <div> tag from beforeDiscussionContent.
     *
     * @return void.
     */
    public function base_afterDiscussionContent_handler() {
        echo '</div>';
    }

    public function base_beforeDiscussionDisplay_handler($sender, $args) {
        ?>
        <div class="RatingContainer RatingDiscussion">
            <a class="RatingUp" DiscussionID="<?= $args['Discussion']->DiscussionID ?>"><?= t('&#x25B2;') ?></a>
            <span class="Rating"><?= intval($args['Discussion']->Score) ?></span>
            <a class="RatingDown" DiscussionID="<?= $args['Discussion']->DiscussionID ?>"><?= t('&#x25BC;') ?></a>
        </div>
        <?php
    }

    public function base_beforeCommentDisplay_handler($sender, $args) {
        ?>
        <div class="RatingContainer RatingComment">
            <a class="RatingUp" CommentID="<?= $args['Comment']->CommentID ?>"><?= t('&#x25B2;') ?></a>
            <span class="Rating"><?= intval($args['Comment']->Score) ?></span>
            <a class="RatingDown" CommentID="<?= $args['Comment']->CommentID ?>"><?= t('&#x25BC;') ?></a>
        </div>
        <?php
    }

    /**
     * API endpoint for changing a discussion/comments rating.
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
        $sender->permission(
            [
                'Plugins.Rating.Add', 'Plugins.Rating.Manage'
            ],
            false
        );
        $getParams = Gdn::request()->getRequestArguments('get');
        // Check for valid TransientKey to prevent clickbaiting.
        if (!Gdn::session()->validateTransientKey(val('tk', $getParams, false))) {
            throw new InvalidArgumentException('TransientKey invalid.');
        }
        // Sanity check for post id.
        $postID = intval(val('id', $getParams, 0));
        if ($postID <= 0) {
            throw new InvalidArgumentException('PostID invalid.');
        }
        // Get post type
        if (val('type', $getParams, 'Discussion') == 'comment') {
            $postType = 'Comment';
        } else {
            $postType = 'Discussion';
        }
        $modelName = $postType.'Model';
        $postModel = new $modelName();

        // Determine rating.
        if (val('rate', $getParams, 'up') == 'down') {
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

    /**
     * Change comments sort order.
     *
     * @param CommentModel $sender Instance of the calling class.
     *
     * @return void.
     */
    public function commentModel_beforeGet_handler($sender) {
        if (c('Vanilla.Discussions.SortField', '') == 'Score') {
            $sender->orderBy('Score desc');
        }
    }

    /**
     * Change comments sort order.
     *
     * @param CommentModel $sender Instance of the calling class.
     *
     * @return void.
     */
    public function commentModel_beforeGetNew_handler($sender) {
        if (c('Vanilla.Discussions.SortField', '') == 'Score') {
            $sender->orderBy('Score desc');
        }
    }

    /**
     * Change comments sort order.
     *
     * @param CommentModel $sender Instance of the calling class.
     *
     * @return void.
     */
    public function commentModel_beforeGetOffset_handler($sender) {
        if (c('Vanilla.Discussions.SortField', '') == 'Score') {
            $sender->orderBy('Score desc');
        }
    }
}
