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

include(__DIR__.DS.'class.rj_plugin.php');


// TODO: make links point to signin for guests
// Don't change sort order for all views!
// Instead create new menu entry "Recent"|"Top"|...
// Sort comments by rank is a admin config setting
// Option to make "Top" the default page
class RatingPlugin extends RJ_Plugin {
    public function __construct() {
        parent::__construct('rating');
    }

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
        $this->settings()->structure();
    }

    /**
     * Allows enabling plugin and changing sort order.
     *
     * @param SettingsController $sender Instance of the calling class.
     *
     * @return void.
     */
    public function settingsController_rating_create($sender) {
        $this->settings()->index($sender);
    }

    public function base_afterDiscussionFilters_handler($sender) {
        $this->ui()->printDiscussionFilterEntry();
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
        $this->ui()->printRatingTemplate(
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
        $this->ui()->printRatingTemplate(
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
        $this->ui()->printRatingTemplate(
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
        $this->api()->rating(Gdn::session(), Gdn::request()->getRequestArguments('get'));
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
