<?php
global $cfg;
$entryTypes = ThreadEntry::getTypes();
$user = $entry->getUser() ?: $entry->getStaff();
if ($entry->staff && $cfg->hideStaffName())
    $name = __('Staff');
else
    $name = $user ? $user->getName() : $entry->poster;
$avatar = '';
if ($cfg->isAvatarsEnabled() && $user)
    $avatar = $user->getAvatar();
$type = $entryTypes[$entry->type];
$isResponse = ($entry->type != 'M');
?>
<div class="thread-entry <?php echo $type; ?> <?php if ($avatar) echo 'has-avatar'; ?>">
<?php if ($avatar) { ?>
    <div class="entry-avatar">
        <?php echo $avatar; ?>
    </div>
<?php } ?>
    <div class="entry-content">
        <div class="entry-bubble" id="thread-id-<?php echo $entry->getId(); ?>">
            <div class="bubble-body"><?php echo $entry->getBody()->toHtml(); ?></div>
<?php if ($entry->has_attachments) { ?>
            <div class="bubble-attachments"><?php
                foreach ($entry->attachments as $A) {
                    if ($A->inline)
                        continue;
                    $size = '';
                    if ($A->file->size)
                        $size = sprintf('<span class="filesize">%s</span>', Format::file_size($A->file->size));
?>
                <a class="attachment-pill no-pjax"
                    href="<?php echo $A->file->getDownloadUrl(['id' => $A->getId()]); ?>"
                    download="<?php echo Format::htmlchars($A->getFilename()); ?>"
                    target="_blank">
                    <i class="icon-paperclip"></i>
                    <span class="filename truncate"><?php echo Format::htmlchars($A->getFilename()); ?></span>
                    <?php echo $size; ?>
                </a>
<?php   }  ?>
            </div>
<?php } ?>
        </div>
        <div class="entry-meta">
            <span class="entry-author"><?php echo Format::htmlchars($name); ?></span>
            <span class="entry-time">
                <time datetime="<?php echo date(DateTime::W3C, Misc::db2gmtime($entry->created)); ?>"
                    title="<?php echo Format::daydatetime($entry->created); ?>">
                    <?php echo Format::datetime($entry->created); ?>
                </time>
            </span>
<?php if ($entry->flags & ThreadEntry::FLAG_EDITED) { ?>
            <span class="entry-edited" title="<?php
                echo sprintf(__('Edited on %s'), Format::datetime($entry->updated)); ?>">
                <?php echo __('Edited'); ?>
            </span>
<?php } ?>
        </div>
    </div>
</div>
<?php if ($urls = $entry->getAttachmentUrls()) { ?>
<script type="text/javascript">
    $('#thread-id-<?php echo $entry->getId(); ?>')
        .data('urls', <?php echo JsonDataEncoder::encode($urls); ?>)
        .data('id', <?php echo $entry->getId(); ?>);
</script>
<?php } ?>
