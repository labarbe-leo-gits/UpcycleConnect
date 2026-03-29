<?php

$title = 'Tip details';
include_once '../../includes/customers-header.php';

requireUserType(1);
$user = getLoggedInUser();

?>

<div class="container">
    <div class="tip-page" id="tip-page">
        <div class="tip-detail" id="tip-detail">
            <div class="skeleton-tip-details" id="tip-loading">
                <div class="skeleton-line" style="width: 50%;"></div>
                <div class="skeleton-line" style="width: 80%;"></div>
                <div class="skeleton-line" style="width: 80%; height: 120px;"></div>
                <div class="skeleton-line" style="width: 40%;"></div>
            </div>
            <div id="tip-content" style="display:none;"></div>
        </div>

        <div class="tip-comments" id="tip-comments">
            <div class="comment-submit">
                <h3>Post a comment</h3>
                <textarea id="comment-input" rows="3" placeholder="Share your view..."></textarea>
                <div class="comment-counter" id="comment-counter">0 / 1500</div>
                <button class="btn-primary" id="comment-submit" style="margin-bottom:20px;">Send</button>
            </div>
            <div class="skeleton-comments" id="comments-loading">
                <div class="skeleton-line" style="width: 80%;"></div>
                <div class="skeleton-line" style="width: 70%;"></div>
                <div class="skeleton-line" style="width: 90%;"></div>
            </div>
            <div id="comments-list" style="display:none;"></div>
            <div class="comment-pagination" id="comments-pagination"></div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script src="../../assets/js/tip-view.js"></script>

<?php include_once '../../includes/footer.php'; ?>