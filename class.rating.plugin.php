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

class RatingPlugin extends Gdn_Plugin {
    public function setup() {
        // TODO: put this into settings
        saveToConfig('Vanilla.Discussions.SortField', 'Score');
        $this->structure();
    }

    // TODO: not needed!
    // Use UserDiscussion/UserComment ->Score
    public function structure(){
        // Index on Score for Discussion and Comment
        // Replace null with zero in Score
        Gdn::structure()->table('Discussion')
            ->column('Score', 'int(11)', 0, 'index')
            ->set();
        Gdn::structure()->table('Comment')
            ->column('Score', 'int(11)', 0, 'index')
            ->set();
    }

    public function base_render_before($sender, $args) {
        // Include style.
        $sender->addCssFile('rating.css','plugins/rating');

        // Do permission checks only once.
        // Check for viewing permissions
        if (!Gdn::session()->checkPermission('Plugins.Rating.View')) {
            return;
        }
        $sender->RatingVisible = true;

        // Check for adding permissions.
        if (
            Gdn::session()->checkPermission(
                [
                    'Plugins.Rating.Add',
                    'Plugins.Rating.Manage'
                ],
                false
            )
        ) {
            // Include js script only if needed.
            $sender->addJsFile('rating.js','plugins/rating');
            // Add css class to allow styling based on users permissions.
            $sender->CssClass .= ' RatingAllowed';
            // Save if user is allowed to do ratings. TODO: Needed?
            $sender->RatingAllowed = true;
        }
    }

    public function base_beforeDiscussionContent_handler($sender, $args) {
        if (!$sender->RatingVisible) {
            $return;
        }

        ?>
        <div class="RatingContainer RatingDiscussion">
            <span class="RatingUp" DiscussionID="<?= $args['Discussion']->DiscussionID ?>"><?= t('&#x25B2;') ?></span>
            <span class="Rating"><?= intval($args['Discussion']->Score) ?></span>
            <span class="RatingDown" DiscussionID="<?= $args['Discussion']->DiscussionID ?>"><?= t('&#x25BC;') ?></span>
        </div>
        <div class="DiscussionContent">
        <?php
    }

    public function base_afterDiscussionContent_handler($sender, $args) {
        echo '</div>';
    }

    public function pluginController_rating_create($sender, $args) {
        $sender->permission('Plugins.Rating.Add');
        $getParams = Gdn::request()->get();
        try {
            // Check for valid TransientKey to prevent clickbaiting.
            if (!Gdn::session()->validateTransientKey($getParams['tk'])) {
                throw new InvalidArgumentException('TransientKey invalid.');
            }
            // Determine rating.
            if ($getParams['rating'] == 'down') {
                $score = -1;
            } else {
                $score = 1;
            }
            if ($getParams['type'] == 'comment') {
                $postType = 'Comment';
            } else {
                $postType = 'Discussion';
            }
            $modelName = $postType.'Model';
            $postModel = new $modelName();

            $currentScore = $postModel->getUserScore(
                intval($getParams['id']),
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
                intval($getParams['id']),
                Gdn::session()->UserID,
                $newScore
            );

            // Return the score to allow js to update the rating.
            return true;

        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function commentModel_beforeGet_handler($sender) {
        $sender->orderBy('Score', 'desc');
    }
    public function commentModel_beforeGetNew_handler($sender) {
        $sender->orderBy('Score', 'desc');
    }
    public function commentModel_beforeGetOffset_handler($sender) {
        $sender->orderBy('Score', 'desc');
    }
}
