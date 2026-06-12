<?php

use App\Kernel\Constants\UiConfirmConstants;
use App\Kernel\Helpers\ViewHelper;

?>
<div class="modal fade ct-confirm-modal" id="confirmModal" tabindex="-1" aria-hidden="true" aria-labelledby="confirmModalTitle">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmModalTitle"><?= ViewHelper::e(UiConfirmConstants::DEFAULT_TITLE) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= ViewHelper::e(UiConfirmConstants::DEFAULT_CANCEL) ?>"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0" id="confirmModalBody"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="confirmModalCancel" data-bs-dismiss="modal"><?= ViewHelper::e(UiConfirmConstants::DEFAULT_CANCEL) ?></button>
                <button type="button" class="btn btn-primary" id="confirmModalOk"><?= ViewHelper::e(UiConfirmConstants::DEFAULT_OK) ?></button>
            </div>
        </div>
    </div>
</div>
