<?php

use App\Core\View;

View::section('content');
$other = $conversation['other'] ?? [];
$myId = (int) auth_id();
?>
<div class="row g-3">
    <div class="col-lg-4 d-none d-lg-block">
        <div class="list-group shadow-sm" style="max-height:72vh;overflow:auto">
            <?php foreach ($conversations as $item): ?>
                <a class="list-group-item list-group-item-action <?= (int) $item['id'] === (int) $active ? 'active' : '' ?>"
                   href="<?= e(url('dashboard/messages/' . $item['id'])) ?>">
                    <div class="d-flex justify-content-between gap-2">
                        <strong class="small text-truncate">
                            <?= e((string) ($item['other_name'] ?? 'Conversation')) ?>
                        </strong>
                        <?php if ((int) $item['unread'] > 0 && (int) $item['id'] !== (int) $active): ?>
                            <span class="badge rounded-pill text-bg-danger"><?= (int) $item['unread'] ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="small text-truncate <?= (int) $item['id'] === (int) $active ? '' : 'text-muted' ?>">
                        <?= e((string) ($item['last_message_preview'] ?? '')) ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center gap-2">
                <div class="min-w-0">
                    <h6 class="mb-0 text-truncate">
                        <?php if (!empty($other['business_slug'])): ?>
                            <a class="text-decoration-none text-dark" href="<?= e(url('business/' . $other['business_slug'])) ?>">
                                <?= e((string) ($other['business_name'] ?? $other['full_name'] ?? 'Conversation')) ?>
                            </a>
                        <?php else: ?>
                            <?= e((string) ($other['full_name'] ?? 'Conversation')) ?>
                        <?php endif; ?>
                        <?php if ((int) ($other['kyc_verified'] ?? 0) === 1): ?>
                            <i class="bi bi-patch-check-fill text-teal" title="KYC verified"></i>
                        <?php endif; ?>
                    </h6>
                    <div class="small text-muted text-truncate">
                        <?php if (!empty($conversation['listing_title'])): ?>
                            About: <a href="<?= e(url('listing/' . $conversation['listing_slug'])) ?>"><?= e((string) $conversation['listing_title']) ?></a>
                        <?php elseif (!empty($conversation['requirement_title'])): ?>
                            About: <a href="<?= e(url('wanted/' . $conversation['requirement_slug'])) ?>"><?= e((string) $conversation['requirement_title']) ?></a>
                        <?php elseif (!empty($conversation['order_reference'])): ?>
                            Order <?= e((string) $conversation['order_reference']) ?>
                        <?php else: ?>
                            Direct message
                        <?php endif; ?>
                    </div>
                </div>
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown" type="button">
                        <i class="bi bi-three-dots-vertical"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <form method="post" action="<?= e(url('dashboard/messages/' . $conversation['id'] . '/block')) ?>"
                                  data-confirm="Block this conversation? Neither side can send more messages.">
                                <?= csrf_field() ?>
                                <button class="dropdown-item text-danger" type="submit">
                                    <i class="bi bi-slash-circle me-2"></i>Block conversation
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="chat-thread p-3" id="chat-thread"
                 data-conversation-id="<?= (int) $conversation['id'] ?>"
                 data-last-id="<?= (int) $last_id ?>">
                <?php if ($messages === []): ?>
                    <p class="small text-muted text-center my-4">No messages yet — say hello.</p>
                <?php endif; ?>

                <?php foreach ($messages as $message): ?>
                    <?php if ($message['message_type'] === 'system'): ?>
                        <div class="chat-bubble system">
                            <?= e((string) $message['body']) ?>
                            <div class="chat-meta"><?= e(fmt_dt($message['created_at'], 'd M, h:i A')) ?></div>
                        </div>
                    <?php else: ?>
                        <div class="chat-bubble <?= (int) $message['sender_id'] === $myId ? 'me' : 'them' ?>">
                            <?= nl2br(e((string) $message['body'])) ?>
                            <?php foreach ($attachments[(int) $message['id']] ?? [] as $file): ?>
                                <?php if (str_starts_with((string) ($file['mime_type'] ?? ''), 'image/')): ?>
                                    <a href="<?= e(upload_url((string) $file['file_path'])) ?>" target="_blank" rel="noopener">
                                        <img src="<?= e(upload_url((string) $file['file_path'])) ?>" class="rounded mt-1" style="max-width:180px" alt="">
                                    </a>
                                <?php else: ?>
                                    <a class="d-block small mt-1" href="<?= e(upload_url((string) $file['file_path'])) ?>"
                                       target="_blank" rel="noopener">
                                        <i class="bi bi-paperclip"></i> <?= e((string) $file['original_name']) ?>
                                        (<?= e(human_bytes((int) $file['file_size'])) ?>)
                                    </a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <div class="chat-meta"><?= e(fmt_dt($message['created_at'], 'd M, h:i A')) ?></div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <div class="card-footer bg-white">
                <?php if ((int) ($conversation['is_blocked'] ?? 0) === 1): ?>
                    <p class="small text-muted mb-0 text-center">This conversation is blocked.</p>
                <?php else: ?>
                    <form id="chat-form" method="post" action="<?= e(url('dashboard/messages/' . $conversation['id'])) ?>"
                          enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <div class="d-flex gap-2 align-items-end">
                            <label class="btn btn-outline-secondary mb-0" for="chat-attachments" title="Attach files">
                                <i class="bi bi-paperclip"></i>
                                <input id="chat-attachments" name="attachments[]" type="file" class="d-none" multiple
                                       accept=".pdf,.jpg,.jpeg,.png,.webp">
                            </label>
                            <textarea id="chat-input" name="body" class="form-control" rows="1"
                                      placeholder="Write a message… (Enter sends, Shift+Enter for a new line)"
                                      maxlength="5000"></textarea>
                            <button class="btn btn-teal" type="submit"><i class="bi bi-send"></i></button>
                        </div>
                        <div id="chat-attachment-preview" class="d-flex flex-wrap gap-2 mt-2"></div>
                    </form>
                    <p class="small text-muted mt-2 mb-0">
                        Keep the deal on the platform — off-platform payments are not covered by dispute resolution.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php View::endSection(); ?>

<?php View::section('scripts'); ?>
<script src="<?= e(asset('js/chat.js')) ?>"></script>
<?php View::endSection(); ?>
