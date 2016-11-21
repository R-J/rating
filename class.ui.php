<?php
/**
 * UI handles all interface relevant methods.
 *
 * Mainly it will print out needed extra bits of html code.
 */
class UI extends RJ_Plugin {
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

    public function printDiscussionFilterEntry() {
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

    public function printRatingTemplate($postType, $postID, $score) {
        ?>
        <div class="RatingContainer Rating<?= $postType ?>" data-posttype="<?= $postType ?>" data-postid="<?= $postID ?>">
            <a class="RatingUp"><?= t('&#x25B2;') ?></a>
            <span class="Rating" data-rating="<?= $score ?>"><?= $score ?></span>
            <a class="RatingDown"><?= t('&#x25BC;') ?></a>
        </div>
        <?php
    }
}
