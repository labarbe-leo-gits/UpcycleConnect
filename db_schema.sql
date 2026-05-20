DROP DATABASE IF EXISTS upcycle;
DROP DATABASE IF EXISTS glpi;

CREATE DATABASE upcycle;
ALTER DATABASE upcycle CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE DATABASE glpi;
ALTER DATABASE glpi CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'glpi_user'@'%' IDENTIFIED BY 'glpi_secret';

GRANT ALL PRIVILEGES ON upcycle.* TO 'app'@'%';

GRANT ALL PRIVILEGES ON glpi.* TO 'glpi_user'@'%';

FLUSH PRIVILEGES;

USE upcycle;

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS users (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    first_name VARCHAR(60) NOT NULL,
    last_name VARCHAR(60) NOT NULL,
    company_name VARCHAR(255) NULL,
    stripe_account_id VARCHAR(255) NULL,
    username VARCHAR(255) NOT NULL UNIQUE,
    balance DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    upcycling_score DOUBLE NOT NULL DEFAULT 0.0,
    is_premium INT DEFAULT 0,
    is_active INT DEFAULT 1,
    stripe_customer_id VARCHAR(255) NULL,
    stripe_subscription_id VARCHAR(255) NULL,
    user_type INT NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL DEFAULT NULL,
    oauth_provider VARCHAR(20) NULL,
    user_secret VARCHAR(255) NULL,
    twofa_enabled BOOLEAN DEFAULT FALSE,
    twofa_secret VARCHAR(64) NULL,
    twofa_backup_codes TEXT NULL,
    oauth_id VARCHAR(255) NULL,
    profile_picture VARCHAR(500) NULL,
    LLM_quota INT NOT NULL, /* User : 10/J, Pro : 15/J FREE, 25/J Premium, Employees: 20/J,Admin: 50/J */
    LLM_usage_today INT NOT NULL DEFAULT 0,
    manager_id CHAR(36) NULL,
    user_level INT NOT NULL DEFAULT 0,
    user_xp INT NOT NULL DEFAULT 0,
    user_road VARCHAR(255) NULL,
    user_city VARCHAR(80) NULL,
    user_zip_code CHAR(5) NULL,
    user_road_number INT NULL,
    siret VARCHAR(14) NULL,
    newsletter_subscribed INT NOT NULL DEFAULT 1,
    newsletter INT NOT NULL DEFAULT 0,
    updoc_quota INT NOT NULL DEFAULT 15, /* Quota for user_type = 2 only. Having a premium subscription will lift to infinity. Once expiraing, we keep 10 days before deleting newest of them. */
    private_profile INT NOT NULL DEFAULT 0,
    friends_requests_settings INT NOT NULL DEFAULT 0, -- 0: everyone, 1: friends of friends, 2: no one
    FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE INDEX idx_oauth (oauth_provider, oauth_id)
);

CREATE TABLE IF NOT EXISTS user_profile_picture_history (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    user_id CHAR(36) NOT NULL,
    picture VARCHAR(500) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS pending_registrations (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    first_name VARCHAR(60) NOT NULL,
    last_name VARCHAR(60) NOT NULL,
    company_name VARCHAR(255) NULL,
    siret VARCHAR(14) NULL,
    user_type INT(11) NOT NULL,
    username VARCHAR(255) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    llm_quota INT(11) NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS password_resets (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    user_id CHAR(36) NOT NULL,
    email VARCHAR(255) NOT NULL,
    code VARCHAR(10) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    used_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY idx_password_resets_email (email),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS categories (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    name VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS badges (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    name VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS user_badges (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    user_id CHAR(36) NOT NULL,
    badge_id CHAR(36) NOT NULL,
    awarded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (badge_id) REFERENCES badges(id) ON DELETE CASCADE,
    UNIQUE INDEX idx_user_badge (user_id, badge_id)
);

CREATE TABLE IF NOT EXISTS annonces(
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    user_id CHAR(36) NOT NULL,
    status INT NOT NULL DEFAULT 0,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    view_count INT NOT NULL DEFAULT 0,
    price DECIMAL(10, 2) NOT NULL,
    poids_materiaux DOUBLE DEFAULT NULL,
    facteur_id CHAR(36) DEFAULT NULL,
    type_materiaux VARCHAR(100) DEFAULT NULL,
    item_state INT NOT NULL DEFAULT 0,
    category_id CHAR(36) NULL,
    ad_campaign_id CHAR(36) NULL,
    upcycling_score DOUBLE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (facteur_id) REFERENCES facteurs_materiaux(id)
);

CREATE TABLE IF NOT EXISTS conteneurs(
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    conteneur_name VARCHAR(120) NOT NULL,
    conteneur_city VARCHAR(80) NOT NULL,
    conteneur_road VARCHAR(255) NOT NULL,
    conteneur_number VARCHAR(20) NOT NULL,
    conteneur_zip_code CHAR(5) NOT NULL,
    capacity INT NOT NULL DEFAULT 100,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS demandes_depot (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    user_id CHAR(36) NOT NULL,
    conteneur_id CHAR(36) NOT NULL,
    object_name VARCHAR(80) NOT NULL,
    object_state INT NOT NULL,
    object_category_id CHAR(36) NULL,
    object_description TEXT NOT NULL,
    status INT NOT NULL DEFAULT 0,
    barcode VARCHAR(128) NULL UNIQUE,
    retrieval_code VARCHAR(6) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (object_category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (conteneur_id) REFERENCES conteneurs(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS demandes_depot_files (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    deposit_id CHAR(36) NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (deposit_id) REFERENCES demandes_depot(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS planning (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    date DATE NOT NULL,
    user_id CHAR(36) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS evenements (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    maximum_participants INT,
    current_participants INT DEFAULT 0,
    /* event_type INT NOT NULL, */
    event_type CHAR(36) NOT NULL,
    event_date DATE NOT NULL,
    event_road VARCHAR(255),
    event_city VARCHAR(80),
    event_zip_code CHAR(5),
    recurring INT NOT NULL DEFAULT 0,
    onlineMeetingLink VARCHAR(255) NULL,
    meetingType VARCHAR(20) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'published',
    created_by CHAR(36) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (event_type) REFERENCES typesPrestations(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS event_availability(
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    event_id CHAR(36) NOT NULL,
    hour INT NOT NULL,
    is_available BOOLEAN NOT NULL DEFAULT TRUE,
    max_participants INT NULL,
    current_participants INT NOT NULL DEFAULT 0,
    FOREIGN KEY (event_id) REFERENCES evenements(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS conseils (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    poll_id CHAR(36) DEFAULT NULL,
    conseil_type INT NOT NULL DEFAULT 0,
    created_by CHAR(36) NOT NULL,
    updated_by CHAR(36) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS conseils_comments (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    conseil_id CHAR(36) NOT NULL,
    parent_id CHAR(36) DEFAULT NULL,
    user_id CHAR(36) NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (conseil_id) REFERENCES conseils(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES conseils_comments(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS conseils_likes (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    conseil_id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conseil_id) REFERENCES conseils(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE (conseil_id, user_id)
);

CREATE TABLE IF NOT EXISTS conseils_reactions (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    conseil_id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    reaction_type TINYINT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (conseil_id) REFERENCES conseils(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE (conseil_id, user_id)
);

CREATE TABLE IF NOT EXISTS conseils_reviews (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    conseil_id CHAR(36) NOT NULL,
    manager_id CHAR(36) NOT NULL,
    review_type ENUM('comment','status','request_changes') NOT NULL DEFAULT 'comment',
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conseil_id) REFERENCES conseils(id) ON DELETE CASCADE,
    FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS conseils_history (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    conseil_id CHAR(36) NOT NULL,
    previous_title VARCHAR(255),
    previous_description TEXT,
    new_title VARCHAR(255),
    new_description TEXT,
    edited_by CHAR(36) NOT NULL,
    edited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conseil_id) REFERENCES conseils(id) ON DELETE CASCADE,
    FOREIGN KEY (edited_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS orders (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    user_id CHAR(36) NOT NULL,
    event_id CHAR(36),
    product_id CHAR(36),
    event_availability_id CHAR(36),
    transaction_id VARCHAR(255) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    status INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES evenements(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES annonces(id) ON DELETE CASCADE,
    FOREIGN KEY (event_availability_id) REFERENCES event_availability(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS reservations (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    user_id CHAR(36) NOT NULL,
    event_id CHAR(36) NOT NULL,
    event_availability_id CHAR(36) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES evenements(id) ON DELETE CASCADE,
    FOREIGN KEY (event_availability_id) REFERENCES event_availability(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS notifications (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    campaign_id CHAR(36) NULL,
    annonce_id CHAR(36),
    user_id CHAR(36) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (annonce_id) REFERENCES annonces(id) ON DELETE CASCADE,
    INDEX idx_notifications_campaign_id (campaign_id)
);

CREATE TABLE IF NOT EXISTS notification_campaigns (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    target_user_type INT NOT NULL,
    status INT NOT NULL DEFAULT 0,
    scheduled_at DATETIME NULL,
    created_by_user_id CHAR(36) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_notification_campaigns_status (status),
    INDEX idx_notification_campaigns_target (target_user_type),
    INDEX idx_notification_campaigns_created_at (created_at)
);

CREATE TABLE IF NOT EXISTS notification_campaign_recipients (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    campaign_id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    notification_id CHAR(36) NULL,
    delivery_status INT NOT NULL DEFAULT 0,
    sent_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES notification_campaigns(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE SET NULL,
    UNIQUE KEY uniq_campaign_recipient (campaign_id, user_id),
    INDEX idx_campaign_recipients_campaign (campaign_id),
    INDEX idx_campaign_recipients_status (delivery_status)
);

CREATE TABLE IF NOT EXISTS facteurs_materiaux (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    nom VARCHAR(100) UNIQUE NOT NULL,
    facteur_co2 DOUBLE NOT NULL,
    facteur_energie DOUBLE NOT NULL
);

CREATE TABLE IF NOT EXISTS ban (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    user_id CHAR(36) NOT NULL,
    reason TEXT NOT NULL,
    banned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    banned_by CHAR(36) NOT NULL,
    duration_days INT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS bankingDetails (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    user_id CHAR(36) NOT NULL,
    rib VARCHAR(255) NOT NULL,
    iban VARCHAR(255) NOT NULL,
    bic VARCHAR(11) NOT NULL,
    account_holder_name VARCHAR(255) NOT NULL,
    is_saved TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS paymentsRequests (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    user_id CHAR(36) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    status INT NOT NULL DEFAULT 0,
    banking_details_id CHAR(36) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (banking_details_id) REFERENCES bankingDetails(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS payouts (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    user_id CHAR(36) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    status INT NOT NULL DEFAULT 0,
    payment_request_id CHAR(36) NOT NULL,
    done_by CHAR(36) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_request_id) REFERENCES paymentsRequests(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS reports (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    reporter_id CHAR(36) NOT NULL,
    reported_user_id CHAR(36),
    reported_annonce_id CHAR(36),
    reported_forum_post_id CHAR(36),
    reported_forum_id CHAR(36),
    reason TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reported_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reported_annonce_id) REFERENCES annonces(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS forum(
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    created_by CHAR(36) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS forum_posts (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    forum_id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    parent_id CHAR(36) NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (forum_id) REFERENCES forum(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES forum_posts(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS discussions (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    user1_id CHAR(36) NOT NULL,
    user2_id CHAR(36) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user1_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (user2_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS group_discussions (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    title VARCHAR(255) NOT NULL,
    image_url VARCHAR(500) DEFAULT NULL,
    created_by CHAR(36) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS group_discussion_members (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    group_discussion_id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_discussion_id) REFERENCES group_discussions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS messages (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    discussion_id CHAR(36),
    group_discussion_id CHAR(36),
    sender_id CHAR(36) NOT NULL,
    content TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (discussion_id) REFERENCES discussions(id) ON DELETE CASCADE,
    FOREIGN KEY (group_discussion_id) REFERENCES group_discussions(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS message_attachments (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    message_id CHAR(36) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS tips (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    poll_id CHAR(36) NULL,
    created_by CHAR(36) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_by CHAR(36) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS api_keys (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    content TEXT NOT NULL,
    created_by CHAR(36) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS refundsRequests (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    order_id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    reason TEXT NOT NULL,
    status INT NOT NULL DEFAULT 0,
    admin_comment TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    approved_by CHAR(36) NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS refunds (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    refund_request_id CHAR(36) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    status INT NOT NULL DEFAULT 0,
    processed_by CHAR(36) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (refund_request_id) REFERENCES refundsRequests(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS projects (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    user_id CHAR(36) NOT NULL,
    annonce_id CHAR(36) NULL,
    title VARCHAR(255) NOT NULL,
    ai_generated INT NOT NULL DEFAULT 0,
    description TEXT NOT NULL,
    status INT NOT NULL DEFAULT 0,
    views INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (annonce_id) REFERENCES annonces(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS project_steps (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    project_id CHAR(36) NOT NULL,
    step_order INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    duration_minutes INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS project_step_materials (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    step_id CHAR(36) NOT NULL,
    facteur_id CHAR(36) NOT NULL,
    quantity DOUBLE NULL,
    FOREIGN KEY (step_id) REFERENCES project_steps(id) ON DELETE CASCADE,
    FOREIGN KEY (facteur_id) REFERENCES facteurs_materiaux(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS images (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    annonce_id CHAR(36),
    event_id CHAR(36),
    step_id CHAR(36),
    file_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (annonce_id) REFERENCES annonces(id) ON DELETE CASCADE,
    FOREIGN KEY (step_id) REFERENCES project_steps(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS project_likes (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    project_id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE INDEX idx_project_like (project_id, user_id),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS project_comments (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    project_id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    parent_id CHAR(36) NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES project_comments(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS tags (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    name VARCHAR(255) NOT NULL UNIQUE,
    bg_color VARCHAR(7) NOT NULL,
    text_color VARCHAR(7) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS item_have_tags (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    tag_id CHAR(36) NOT NULL,
    annonce_id CHAR(36) NULL,
    event_id CHAR(36) NULL,
    project_id CHAR(36) NULL,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE,
    FOREIGN KEY (annonce_id) REFERENCES annonces(id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES evenements(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS polls (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    question VARCHAR(255) NOT NULL,
    created_by CHAR(36) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS poll_options (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    poll_id CHAR(36) NOT NULL,
    option_text VARCHAR(255) NOT NULL,
    FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS poll_votes (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    poll_id CHAR(36) NOT NULL,
    option_id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE,
    FOREIGN KEY (option_id) REFERENCES poll_options(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE INDEX idx_poll_vote (poll_id, user_id)
);

CREATE TABLE IF NOT EXISTS typesPrestations (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    name VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS affectedEmployees (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    user_id CHAR(36) NOT NULL,
    event_id CHAR(36) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES evenements(id) ON DELETE CASCADE
);

-- Contracts / Subscriptions / Ads
CREATE TABLE IF NOT EXISTS contracts (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    contract_ref VARCHAR(64) NULL,
    contract_type TINYINT NOT NULL DEFAULT 1,
    subscriptionID VARCHAR(255) NOT NULL,
    stripe_customer_id VARCHAR(255) NULL,
    stripe_price_id VARCHAR(255) NULL,
    stripe_product_id VARCHAR(255) NULL,
    user_id CHAR(36) NOT NULL,
    amount DECIMAL(10,2) NULL,
    currency CHAR(3) NOT NULL DEFAULT 'EUR',
    billing_interval VARCHAR(16) NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    cancel_at_period_end BOOLEAN NOT NULL DEFAULT FALSE,
    cancelled_at TIMESTAMP NULL DEFAULT NULL,
    stripe_subscription_status VARCHAR(50) NULL,
    status INT NOT NULL DEFAULT 0,
    metadata JSON NULL,
    last_billed_at TIMESTAMP NULL DEFAULT NULL,
    next_billing_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE INDEX idx_contract_subscription (subscriptionID),
    INDEX idx_contract_user (user_id),
    INDEX idx_contract_type (contract_type)
);

CREATE TABLE IF NOT EXISTS invoices (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    user_id CHAR(36) NOT NULL,
    contract_id CHAR(36) NULL,
    stripe_invoice_id VARCHAR(255) NULL,
    stripe_payment_intent_id VARCHAR(255) NULL,
    amount_due DECIMAL(10,2) NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0,
    currency CHAR(3) NOT NULL DEFAULT 'EUR',
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    due_date DATE NULL,
    period_start DATE NULL,
    period_end DATE NULL,
    invoice_url VARCHAR(512) NULL,
    receipt_url VARCHAR(512) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE SET NULL,
    INDEX idx_invoice_contract (contract_id),
    INDEX idx_invoice_user (user_id),
    INDEX idx_invoice_status (status),
    UNIQUE INDEX idx_invoice_stripe_invoice (stripe_invoice_id)
);

CREATE TABLE IF NOT EXISTS invoice_items (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    invoice_id CHAR(36) NOT NULL,
    description VARCHAR(512) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    currency CHAR(3) NOT NULL DEFAULT 'EUR',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS ad_campaigns (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    user_id CHAR(36) NOT NULL,
    contract_id CHAR(36) NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    status TINYINT NOT NULL DEFAULT 0 COMMENT '0=draft,1=active,2=paused,3=cancelled',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    budget DECIMAL(10,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'EUR',
    stripe_payment_intent_id VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS materiaux_manipules(
    proID CHAR(36) NOT NULL,
    facteurID CHAR(36) NOT NULL,
    PRIMARY KEY (proID, facteurID),
    FOREIGN KEY (proID) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (facteurID) REFERENCES facteurs_materiaux(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS types_prestations_pro(
    proID CHAR(36) NOT NULL,
    typePrestationID CHAR(36) NOT NULL,
    PRIMARY KEY (proID, typePrestationID),
    FOREIGN KEY (proID) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (typePrestationID) REFERENCES typesPrestations(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS litiges (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    order_id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    reason TEXT NOT NULL,
    status INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

/* Default Admin */
INSERT INTO users (first_name, last_name, username, email, password_hash, user_type, is_active, LLM_quota) VALUES ('Admin', 'Upcycle', 'admin', 'admin@upcycleconnect.cloud', '$2a$12$C/CCS/1leF1IJMkUZPWLiu78ja2wnfJN2LDCrBa6MuZuL4CPc/rLa', 3, 1, 50);

/* To IMPLEMENT FRONT END WISE
- Litiges
- Types Prestations Pro
- Validation Comptes Pro + Sire(t)(n)
- Matériaux manipulés par pro
- types prestations pro
- Conseils + sondages
- refunds
- reports
- dispo employé pour event
- ajout après achat service au calendrier
- affichage des items dans le conteneur pour le pro
- statut récupéré
- payout
- modération
- mode dark / light 100%
- pdf
- glpi
- dispos d'event à certaines horaires pour les formations par exemple
- heure dans les services
- récurrent ?

/!\ IL FAUT AUSSI LE FRONTEND

 */

CREATE TABLE IF NOT EXISTS friendships (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    user_id CHAR(36) NOT NULL,
    friend_id CHAR(36) NOT NULL,
    status INT NOT NULL DEFAULT 0, -- 0: pending, 1: accepted
    message TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (friend_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE INDEX idx_friendship (user_id, friend_id)
);

CREATE TABLE IF NOT EXISTS friendship_requests (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    sender_id CHAR(36) NOT NULL,
    receiver_id CHAR(36) NOT NULL,
    message TEXT DEFAULT NULL,
    status INT NOT NULL DEFAULT 0, -- 0: pending, 1: accepted, 2: rejected
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE INDEX idx_friendship_request (sender_id, receiver_id)
);

CREATE TABLE IF NOT EXISTS contacts (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS contact_responses (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    contact_id CHAR(36) NOT NULL,
    responder_id CHAR(36) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
    FOREIGN KEY (responder_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS userReviews (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    reviewer_id CHAR(36) NOT NULL,
    reviewed_user_id CHAR(36) NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS newsletter(
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    status INT NOT NULL DEFAULT 0, -- 0: draft, 1: scheduled, 2: sent
    created_by_user_type INT NULL, -- 3: admin, 4: partials employee
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS newsletter_recipients (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    newsletter_id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    sent_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (newsletter_id) REFERENCES newsletter(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE INDEX idx_newsletter_recipient (newsletter_id, user_id)
);

CREATE TABLE IF NOT EXISTS favorites (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    user_id CHAR(36) NOT NULL,
    annonce_id CHAR(36) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (annonce_id) REFERENCES annonces(id) ON DELETE CASCADE,
    UNIQUE INDEX idx_favorite (user_id, annonce_id)
);

CREATE TABLE IF NOT EXISTS subscription_tiers (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT NULL,
    tier_level INT NOT NULL UNIQUE,
    monthly_price DECIMAL(10,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'EUR',
    stripe_price_id VARCHAR(255) NULL,
    features JSON NULL,
    dashboard_access BOOLEAN DEFAULT FALSE,
    analytics_access BOOLEAN DEFAULT FALSE,
    material_stats BOOLEAN DEFAULT FALSE,
    collection_alerts BOOLEAN DEFAULT FALSE,
    max_annonces INT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT IGNORE INTO subscription_tiers (
    name, description, tier_level, monthly_price, currency, stripe_price_id,
    features, dashboard_access, analytics_access, material_stats, collection_alerts,
    max_annonces, is_active
) VALUES
    (
        'Free',
        'Basic access for individuals getting started.',
        0,
        0.00,
        'EUR',
        NULL,
        JSON_ARRAY('Post listings', 'Access to containers', 'Community forum', 'Messaging', 'Basic statistics'),
        FALSE,
        FALSE,
        FALSE,
        FALSE,
        5,
        TRUE
    ),
    (
        'Pro',
        'Professional plan for active sellers and craftspeople.',
        1,
        19.99,
        'EUR',
        NULL,
        JSON_ARRAY('Advanced dashboards', 'Material statistics', 'Priority alerts', 'Detailed activity overview'),
        TRUE,
        TRUE,
        TRUE,
        TRUE,
        50,
        TRUE
    ),
    (
        'Premium',
        'Full access plan with advanced analytics and priority support.',
        2,
        29.99,
        'EUR',
        'price_1T70WSI7wZRSS0GxZYKgEKZS',
        JSON_ARRAY('Everything in Pro', 'Ecological impact analysis', 'Advanced dashboards', 'Priority support'),
        TRUE,
        TRUE,
        TRUE,
        TRUE,
        NULL,
        TRUE
    );

CREATE TABLE IF NOT EXISTS user_subscription_tiers (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    user_id CHAR(36) NOT NULL,
    tier_id CHAR(36) NOT NULL,
    contract_id CHAR(36) NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (tier_id) REFERENCES subscription_tiers(id) ON DELETE CASCADE,
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE SET NULL,
    INDEX idx_user_subscription (user_id, started_at)
);

CREATE TABLE IF NOT EXISTS commission_settings (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    commission_rate_min DECIMAL(5,2) NOT NULL DEFAULT 5.00,
    commission_rate_max DECIMAL(5,2) NOT NULL DEFAULT 10.00,
    is_global BOOLEAN DEFAULT TRUE,
    effective_from DATE NOT NULL,
    effective_to DATE NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS commission_transactions (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    order_id CHAR(36) NOT NULL,
    seller_id CHAR(36) NOT NULL,
    amount_before_commission DECIMAL(10,2) NOT NULL,
    commission_rate DECIMAL(5,2) NOT NULL,
    commission_amount DECIMAL(10,2) NOT NULL,
    amount_after_commission DECIMAL(10,2) NOT NULL,
    status INT NOT NULL DEFAULT 0,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_commission_seller (seller_id),
    INDEX idx_commission_status (status)
);

CREATE TABLE IF NOT EXISTS partnership_campaigns (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    partner_name VARCHAR(255) NOT NULL,
    partner_logo VARCHAR(500) NULL,
    description TEXT NULL,
    website_url VARCHAR(500) NULL,
    status INT NOT NULL DEFAULT 0,
    monthly_price DECIMAL(10,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'EUR',
    contract_id CHAR(36) NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    stripe_payment_intent_id VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE SET NULL,
    INDEX idx_partnership_status (status),
    INDEX idx_partnership_dates (start_date, end_date)
);

CREATE TABLE IF NOT EXISTS partnership_campaign_items (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    campaign_id CHAR(36) NOT NULL,
    annonce_id CHAR(36) NULL,
    position_type VARCHAR(50) NOT NULL,
    position_priority INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES partnership_campaigns(id) ON DELETE CASCADE,
    FOREIGN KEY (annonce_id) REFERENCES annonces(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS training_sessions (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    event_id CHAR(36) NOT NULL,
    creator_id CHAR(36) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    session_type VARCHAR(50) NOT NULL,
    price_per_person DECIMAL(10,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'EUR',
    max_participants INT DEFAULT NULL,
    current_participants INT DEFAULT 0,
    is_online BOOLEAN DEFAULT FALSE,
    online_link VARCHAR(500) NULL,
    status INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES evenements(id) ON DELETE CASCADE,
    FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_training_status (status)
);

CREATE TABLE IF NOT EXISTS training_session_registrations (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    session_id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    order_id CHAR(36) NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    status INT NOT NULL DEFAULT 0,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    attended_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES training_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    UNIQUE INDEX idx_training_registration (session_id, user_id),
    INDEX idx_training_user_registrations (user_id)
);

CREATE TABLE IF NOT EXISTS revenue_reports (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    report_period_start DATE NOT NULL,
    report_period_end DATE NOT NULL,
    subscription_revenue DECIMAL(15,2) DEFAULT 0,
    commission_revenue DECIMAL(15,2) DEFAULT 0,
    partnership_revenue DECIMAL(15,2) DEFAULT 0,
    training_revenue DECIMAL(15,2) DEFAULT 0,
    total_revenue DECIMAL(15,2) DEFAULT 0,
    subscription_count INT DEFAULT 0,
    commission_count INT DEFAULT 0,
    partnership_count INT DEFAULT 0,
    training_participants INT DEFAULT 0,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE INDEX idx_revenue_period (report_period_start, report_period_end)
);

SET FOREIGN_KEY_CHECKS = 1;
