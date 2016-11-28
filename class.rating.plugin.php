<?php

$PluginInfo['rating'] = [
    'Name' => 'Rating',
    'Description' => 'Allows users to up- or down-vote discussions and comments.',
    'Version' => '0.2',
    'RequiredApplications' => ['Vanilla' => '2.3'],
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

/**
 * Allows users to rate discussions and comments.
 *
 * @todo Make links point to signin for guests
 * @todo Option to make "Top" the default page
 */
class RatingPlugin extends Gdn_Plugin {
    /**
     * Init db changes and set default comment sort.
     *
     * @return void.
     */
    public function setup() {
        touchConfig(['rating.Comments.OrderBy' => 'Score desc']);
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
     * Allows changing sort order.
     *
     * @param SettingsController $sender Instance of the calling class.
     *
     * @return void.
     */
    public function settingsController_rating_create($sender) {
        $sender->permission('Garden.Settings.Manage');
        $sender->setData('Title', t('Rating Settings'));
        $sender->addSideMenu('dashboard/settings/plugins');

        // Add form.
        $sender->Form = new Gdn_Form();

        // Choices for sort directions.
        $validSortDirections = ['desc' => 'Descending', 'asc' => 'Ascending'];
        $sender->setData('SortDirection', $validSortDirections);
        // Choices for sort field.
        $validSortFields = ['Score' => 'Score', 'DateInserted' => 'Date Inserted'];
        $sender->setData('SortField', $validSortFields);

        if ($sender->Form->authenticatedPostBack() === false) {
            // If form is displayed "unposted", fill fields with config values.
            $orderBy = explode(' ', c('rating.Comments.OrderBy'));
            $sortField = val(0, $orderBy);
            $sortDirection = val(1, $orderBy);
            if (array_key_exists($sortField, $validSortFields)) {
                $sender->Form->setValue('SortField', $sortField);
            }
            if (array_key_exists($sortDirection, $validSortDirections)) {
                $sender->Form->setValue('SortDirection', $sortDirection);
            }
        } else {
            // After POST, validate input and built/save config string.
            $sortField = $sender->Form->getFormValue('SortField');
            $sortDirection = $sender->Form->getFormValue('SortDirection');
            // Validate sort field.
            if (!array_key_exists($sortField, $validSortFields)) {
                $sender->Form->addError(
                    'Comment Sort Field must be either "Score" or "DateInserted"',
                    'SortField'
                );
            }
            // Validate sort direction.
            if (!array_key_exists($sortDirection, $validSortDirections)) {
                $sender->Form->addError(
                    'Sort Direction must be either "asc" or "desc"',
                    'SortDirection'
                );
            }
            // If there are no validation errors, save config setting.
            if (!$sender->Form->errors()) {
                saveToConfig('rating.Comments.OrderBy', $sortField.' '.$sortDirection);
                $sender->informMessage(
                    sprite('Check', 'InformSprite').t('Your settings have been saved.'),
                    ['CssClass' => 'Dismissable AutoDismiss HasSprite']
                );
            }
        }
        // Show form.
        $sender->render($this->getView('settings.php'));
    }

    /**
     * Add link to "Top" discussions to discussion filter.
     *
     * @param GardenController $sender Instance of the sending class.
     *
     * @return void.
     */
    public function base_afterDiscussionFilters_handler($sender) {
        $cssClass = 'Top';
        // Change class if "Top" is current page.
        if (
            strtolower(Gdn::controller()->RequestMethod) == 'top' &&
            strtolower(Gdn::controller()->ControllerName) == 'discussionscontroller'
        ) {
            $cssClass .= ' Active';
        }
        // Insert link.
        ?>
        <li class="<?= $cssClass ?>">
            <?= anchor(sprite('SpTop').t('Top'), '/discussions/top') ?>
        </li>
        <?php
    }

    /**
     * Add CSS & JS files. Do plugins permission checks.
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

        // Check for adding permissions only once per page call.
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
     * Helper method to print the rating snippet.
     *
     * @param string  $postType Either "Discussion" or "Comment".
     * @param integer $postID   The DiscussionID/CommentID.
     * @param integer $score    The current score of this post.
     *
     * @return void
     */
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
     *
     * @param PluginController $sender Instance of the calling class.
     *
     * @return bool Whether Score has been updated.
     */
    public function pluginController_rating_create($sender) {
        // Store manage permission since it will be needed more often.
        if (Gdn::session()->checkPermission()) {
            $canManage = true;
        } else {
            // If has neither Add nor Manage rights, break.
            if (!Gdn::session()->checkPermission('Plugins.Rating.Add')) {
                throw permissionException('Plugins.Rating.Add');
            }
        }

        // Check for valid TransientKey to prevent clickbaiting.
        if (!Gdn::session()->validateTransientKey(Gdn::request()->get('tk', false))) {
            throw new Exception('Transient Key is invalid.');
        }
        // Sanity check for post id.
        $postID = intval(Gdn::request()->get('id', 0));
        if ($postID <= 0) {
            throw new Exception('Post ID is invalid.');
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
        if (!$canManage) {
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
        // Ensure that users without manage permissions cannot give
        // a score > 1 / < -1.
        if (!$canManage && ($newScore > 1 || $newScore < -1)) {
            return false;
        }
        // Echoes the new total score of the post so that it can be displayed.
        echo $postModel->setUserScore(
            $postID,
            Gdn::session()->UserID,
            $newScore
        );

        // Return true since update was successful.
        return true;
    }

    /**
     * Add new discussion list sorted by column Score.
     *
     * This makes use of the discussion sort feature of Vanilla 2.3. For
     * versions below it would have to be implemented n another way.
     *
     * @param DiscussionsController $sender Instance of the calling class.
     *
     * @return void.
     */
    public function discussionsController_top_create($sender) {
        // "score" sort is removed in index(), so we have to re-insert it
        // before we can add it.
        DiscussionModel::addSort(
            'score',
            'Top',
            ['Score' => 'desc', 'DateInserted' => 'desc']
        );
        Gdn::request()->setRequestArguments('get', ['sort' => 'score']);

        // Add info needed for displaying a discussions page.
        $sender->title(t('Top Rated'));
        $sender->setData('_PagerUrl', 'discussions/top/{Page}');
        $sender->View = 'index';
        $page = val(0, $sender->RequestArgs, '');
        if ($page == 'feed.rss') {
            $page = 'feed';
        }
        // Re-use the original discussions index view.
        $sender->index($page);
    }

    /**
     * Adapt some settings of index view.
     *
     * In order to re-use the discussions index view, we need to change
     * a few settings a) after the index() method has been run and b) before
     * the view is rendered.
     *
     * @param DiscussionsController $sender Instance of the calling class.
     *
     * @return void.
     */
    public function discussionsController_render_before($sender) {
        if (strtolower(Gdn::controller()->RequestMethod) != 'top') {
            return;
        }
        // Change canonical url.
        $sender->canonicalUrl(
            url(
                ConcatSep('/', 'discussions', 'top', $sender->data('_Page')),
                true
            )
        );
        // Correct feed address.
        if ($sender->Head) {
            $sender->Head->addRss(
                url('/discussions/top/feed.rss', true),
                $sender->Head->title()
            );
        }
        // Set the breadcrumbs
        $sender->setData(
            'Breadcrumbs',
            [['Name' => t('Top Rated'), 'Url' => '/discussions/top']]
        );
    }

    /**
     * Change comments sort order.
     *
     * @param CommentModel $sender Instance of the calling class.
     *
     * @return void.
     */
    public function commentModel_afterConstruct_handler($sender) {
        $sender->orderBy(c('rating.Comments.OrderBy', 'Score desc'));
    }
}
