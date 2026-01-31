<?php
if(!defined('OSTCLIENTINC') || !$thisclient || !$ticket || !$ticket->checkUserAccess($thisclient)) die('Access Denied!');

$info=($_POST && $errors)?Format::htmlchars($_POST):array();

$type = array('type' => 'viewed');
Signal::send('object.view', $ticket, $type);

$dept = $ticket->getDept();
$status = $ticket->getStatus();
$statusState = $status ? $status->getState() : 'open';

if ($ticket->isClosed() && !$ticket->isReopenable())
    $warn = sprintf(__('%s is marked as closed and cannot be reopened.'), __('This ticket'));

//Making sure we don't leak out internal dept names
if(!$dept || !$dept->isPublic())
    $dept = $cfg->getDefaultDept();

if ($blockReply = $ticket->isChild() && $ticket->getMergeType() != 'visual')
    $warn = sprintf(__('This Ticket is Merged into another Ticket. Please go to the %s%d%s to reply.'),
        '<a href="tickets.php?id=', $ticket->getPid(), '" style="text-decoration:underline">Parent</a>');

// Get custom fields data
$customSections = $customForms = array();
foreach (DynamicFormEntry::forTicket($ticket->getId()) as $i=>$form) {
    $answers = $form->getAnswers()->exclude(Q::any(array(
        'field__flags__hasbit' => DynamicFormField::FLAG_EXT_STORED,
        'field__name__in' => array('subject', 'priority'),
        Q::not(array('field__flags__hasbit' => DynamicFormField::FLAG_CLIENT_VIEW)),
    )));
    foreach ($answers as $j=>$a) {
        if ($v = $a->display())
            $customSections[$i][$j] = array($v, $a);
    }
    $customForms[$i] = $form->getTitle();
}

$subject_field = TicketForm::getInstance()->getField('subject');
?>

<?php if ($thisclient && $thisclient->isGuest() && $cfg->isClientRegistrationEnabled()) { ?>
<div class="guest-banner">
    <i class="icon-info-circle"></i>
    <span><?php echo __('Looking for your other tickets?'); ?>
    <a href="<?php echo ROOT_PATH; ?>login.php?e=<?php echo urlencode($thisclient->getEmail()); ?>"><?php echo __('Sign In'); ?></a>
    <?php echo __('or'); ?>
    <a href="account.php?do=create"><?php echo __('register for an account'); ?></a></span>
</div>
<?php } ?>

<!-- Jira-style Ticket Header -->
<div class="ticket-header">
    <div class="ticket-breadcrumb">
        <a href="tickets.php"><i class="icon-arrow-left"></i> <?php echo __('Back to Tickets'); ?></a>
    </div>
    <div class="ticket-title-row">
        <div class="ticket-title">
            <span class="ticket-key"><?php echo $ticket->getNumber(); ?></span>
            <h1><?php echo $subject_field->display($ticket->getSubject()); ?></h1>
        </div>
        <div class="ticket-actions">
<?php if ($ticket->hasClientEditableFields() && $thisclient->getId() == $ticket->getUserId()) { ?>
            <a class="btn-action" href="tickets.php?a=edit&id=<?php echo $ticket->getId(); ?>">
                <i class="icon-edit"></i> <?php echo __('Edit'); ?>
            </a>
<?php } ?>
            <a class="btn-action" href="tickets.php?a=print&id=<?php echo $ticket->getId(); ?>">
                <i class="icon-print"></i> <?php echo __('Print'); ?>
            </a>
        </div>
    </div>
</div>

<?php if($errors['err']) { ?>
    <div id="msg_error"><?php echo $errors['err']; ?></div>
<?php }elseif($msg) { ?>
    <div id="msg_notice"><?php echo $msg; ?></div>
<?php }elseif($warn) { ?>
    <div id="msg_warning"><?php echo $warn; ?></div>
<?php } ?>

<!-- Jira-style Two Column Layout -->
<div class="ticket-layout">
    <!-- Main Content Area -->
    <div class="ticket-main">
        <!-- Reply Form -->
<?php if ((!$ticket->isClosed() || $ticket->isReopenable()) && !$blockReply) { ?>
        <div class="reply-section">
            <form id="reply" action="tickets.php?id=<?php echo $ticket->getId(); ?>#reply" name="reply" method="post" enctype="multipart/form-data">
                <?php csrf_token(); ?>
                <input type="hidden" name="id" value="<?php echo $ticket->getId(); ?>">
                <input type="hidden" name="a" value="reply">
                <div class="reply-input-wrapper">
                    <textarea name="<?php echo $messageField->getFormName(); ?>" id="message"
                        placeholder="<?php echo __('Add a comment...'); ?>"
                        class="<?php if ($cfg->isRichTextEnabled()) echo 'richtext'; ?> draft" <?php
                        list($draft, $attrs) = Draft::getDraftAndDataAttrs('ticket.client', $ticket->getId(), $info['message']);
                        echo $attrs; ?>><?php echo $draft ?: $info['message']; ?></textarea>
                    <?php if ($messageField->isAttachmentsEnabled()) {
                        print $attachments->render(array('client'=>true));
                    } ?>
                </div>
<?php if ($ticket->isClosed() && $ticket->isReopenable()) { ?>
                <div class="reopen-notice">
                    <i class="icon-info-circle"></i> <?php echo __('Ticket will be reopened on message post'); ?>
                </div>
<?php } ?>
                <div class="reply-actions">
                    <button type="submit" class="btn-primary">
                        <i class="icon-paper-plane"></i> <?php echo __('Send'); ?>
                    </button>
<?php if ($cfg->allowClientClose() && !$ticket->isClosed() && $thisclient->getId() == $ticket->getUserId()) { ?>
                    <button type="submit" name="close_on_reply" class="btn-secondary"
                        onclick="return confirm('<?php echo __('Are you sure you want to close this ticket?'); ?>');">
                        <i class="icon-check"></i> <?php echo __('Send & Close'); ?>
                    </button>
<?php } ?>
                </div>
                <?php if ($errors['message']) { ?>
                <div class="field-error"><?php echo $errors['message']; ?></div>
                <?php } ?>
            </form>
        </div>
<?php } ?>

        <!-- Activity Section -->
        <div class="activity-section">
            <div class="activity-header">
                <h3><i class="icon-comments"></i> <?php echo __('Activity'); ?></h3>
                <span class="activity-count"><?php
                    $email = $thisclient->getUserName();
                    $clientId = TicketUser::lookupByEmail($email)->getId();
                ?></span>
            </div>
            <div class="activity-feed">
<?php
    $ticket->getThread()->render(array('M', 'R', 'user_id' => $clientId), array(
                    'mode' => Thread::MODE_CLIENT,
                    'html-id' => 'ticketThread',
                    'sort' => 'DESC')
                );
?>
            </div>
        </div>
    </div>

    <!-- Details Sidebar -->
    <div class="ticket-sidebar">
        <!-- Status Card -->
        <div class="detail-card">
            <div class="detail-card-header">
                <h4><?php echo __('Status'); ?></h4>
            </div>
            <div class="detail-card-body">
                <span class="status-badge status-<?php echo $statusState; ?>">
                    <?php echo $status ? $status->getLocalName() : __('Open'); ?>
                </span>
            </div>
        </div>

        <!-- Details Card -->
        <div class="detail-card">
            <div class="detail-card-header">
                <h4><?php echo __('Details'); ?></h4>
            </div>
            <div class="detail-card-body">
                <div class="detail-row">
                    <span class="detail-label"><?php echo __('Department'); ?></span>
                    <span class="detail-value"><?php echo Format::htmlchars($dept instanceof Dept ? $dept->getName() : ''); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><?php echo __('Created'); ?></span>
                    <span class="detail-value"><?php echo Format::datetime($ticket->getCreateDate()); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><?php echo __('Updated'); ?></span>
                    <span class="detail-value"><?php echo Format::datetime($ticket->getLastMsgDate() ?: $ticket->getCreateDate()); ?></span>
                </div>
            </div>
        </div>

        <!-- Reporter Card -->
        <div class="detail-card">
            <div class="detail-card-header">
                <h4><?php echo __('Reporter'); ?></h4>
            </div>
            <div class="detail-card-body">
                <div class="reporter-info">
                    <div class="reporter-avatar">
                        <?php echo mb_strtoupper(mb_substr($ticket->getName()->getFirst(), 0, 1)); ?>
                    </div>
                    <div class="reporter-details">
                        <div class="reporter-name"><?php echo Format::htmlchars($ticket->getName()); ?></div>
                        <div class="reporter-email"><?php echo Format::htmlchars($ticket->getEmail()); ?></div>
                        <?php if ($ticket->getPhoneNumber()) { ?>
                        <div class="reporter-phone"><?php echo $ticket->getPhoneNumber(); ?></div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Custom Fields -->
<?php foreach ($customSections as $i=>$answers) { ?>
        <div class="detail-card">
            <div class="detail-card-header">
                <h4><?php echo $customForms[$i]; ?></h4>
            </div>
            <div class="detail-card-body">
<?php foreach ($answers as $A) { list($v, $a) = $A; ?>
                <div class="detail-row">
                    <span class="detail-label"><?php echo $a->getField()->get('label'); ?></span>
                    <span class="detail-value"><?php echo $v; ?></span>
                </div>
<?php } ?>
            </div>
        </div>
<?php } ?>
    </div>
</div>
<script type="text/javascript">
<?php
// Hover support for all inline images
$urls = array();
foreach (AttachmentFile::objects()->filter(array(
    'attachments__thread_entry__thread__id' => $ticket->getThreadId(),
    'attachments__inline' => true,
)) as $file) {
    $urls[strtolower($file->getKey())] = array(
        'download_url' => $file->getDownloadUrl(['type' => 'H']),
        'filename' => $file->name,
    );
} ?>
showImagesInline(<?php echo JsonDataEncoder::encode($urls); ?>);
</script>
