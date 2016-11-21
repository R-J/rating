<?php

class API extends RJ_Plugin {
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

    public function rating($session, $getParams) {
        // Check for valid TransientKey to prevent clickbaiting.
        if (!$session->validateTransientKey(val('tk', $getParams, false))) {
            throw new InvalidArgumentException('TransientKey invalid.');
        }
        // Sanity check for post id.
        $postID = intval(val('id', $getParams, 0));
        if ($postID <= 0) {
            throw new InvalidArgumentException('PostID invalid.');
        }
        // Get post type
        if (val('type', $getParams, 'Discussion') == 'Comment') {
            $postType = 'Comment';
        } else {
            $postType = 'Discussion';
        }
        $modelName = $postType.'Model';
        $postModel = new $modelName();

        // Prevent users from voting on their own posts.
        if (!$session->checkPermission('Plugins.Rating.Manage')) {
            $post = $postModel->getID($postID);
            if ($post->InsertUserID == $session->UserID) {
                return false;
            }
        }

        // Determine rating.
        if (val('rate', $getParams, 'up') == 'down') {
            $score = -1;
        } else {
            $score = 1;
        }

        $currentScore = $postModel->getUserScore(
            $postID,
            $session->UserID
        );

        $newScore = $currentScore + $score;
        if (
            !$session->checkPermission('Plugins.Rating.Manage') &&
            ($newScore > 1 || $newScore < -1)
        ) {
            // Score cannot exceed -1/1 for normal users. No change is made.
            return false;
        }
        // Echoes the new total score of the post.
        echo $postModel->setUserScore(
            $postID,
            $session->UserID,
            $newScore
        );

        // Return the score to allow js to update the rating.
        return true;
    }
}
