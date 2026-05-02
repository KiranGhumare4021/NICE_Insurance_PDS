
-- Users table for authentication
CREATE TABLE IF NOT EXISTS `JKP_USERS` (
  `USER_ID` int(11) NOT NULL AUTO_INCREMENT,
  `USERNAME` varchar(50) NOT NULL,
  `PASSWORD_HASH` varchar(255) NOT NULL,
  `FULL_NAME` varchar(100) NOT NULL,
  `ROLE` varchar(20) NOT NULL DEFAULT 'customer' COMMENT 'customer or employee',
  `CUSTOMER_ID` smallint(6) DEFAULT NULL COMMENT 'Links to JKP_CUSTOMER if role=customer',
  `CREATED_AT` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`USER_ID`),
  UNIQUE KEY `USERNAME` (`USERNAME`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Login history table for security auditing
CREATE TABLE IF NOT EXISTS `JKP_LOGIN_HISTORY` (
  `LOG_ID` int(11) NOT NULL AUTO_INCREMENT,
  `USER_ID` int(11) NOT NULL,
  `LOGIN_TIME` datetime NOT NULL,
  `IP_ADDRESS` varchar(45) NOT NULL,
  PRIMARY KEY (`LOG_ID`),
  KEY `FK_LOGIN_USER` (`USER_ID`),
  CONSTRAINT `FK_LOGIN_USER` FOREIGN KEY (`USER_ID`) REFERENCES `JKP_USERS` (`USER_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert default admin/employee account (password: admin123)
INSERT INTO `JKP_USERS` (`USERNAME`, `PASSWORD_HASH`, `FULL_NAME`, `ROLE`, `CUSTOMER_ID`)
VALUES ('admin', '$2y$10$YourHashHere', 'System Admin', 'employee', NULL);
-- NOTE: Generate the real hash in PHP with: echo password_hash('admin123', PASSWORD_BCRYPT);
-- Then replace '$2y$10$YourHashHere' with the output.

-- Insert a sample customer account linked to customer 1001 (password: customer123)
-- Same note: generate real hash first
INSERT INTO `JKP_USERS` (`USERNAME`, `PASSWORD_HASH`, `FULL_NAME`, `ROLE`, `CUSTOMER_ID`)
VALUES ('john_smith', '$2y$10$YourHashHere2', 'John Smith', 'customer', 1001);

-- STORED PROCEDURES

DELIMITER //

-- SP1: Get full customer dashboard data
CREATE PROCEDURE sp_get_customer_dashboard(IN p_customer_id SMALLINT)
BEGIN
    -- Customer info
    SELECT * FROM JKP_CUSTOMER WHERE CUSTOMER_ID = p_customer_id;

    -- Auto policies
    SELECT ap.*, v.MAKE, v.MODEL, v.V_YEAR
    FROM JKP_AUTO_POLICY ap
    LEFT JOIN JKP_VEHICLES v ON v.CUSTOMER_ID = ap.CUSTOMER_ID AND v.CUSTOMER_TYPE = ap.CUSTOMER_TYPE
    WHERE ap.CUSTOMER_ID = p_customer_id;

    -- Home policies
    SELECT hp.*, h.HOME_TYPE, h.HOME_VALUE
    FROM JKP_HOME_POLICY hp
    LEFT JOIN JKP_HOMES h ON h.CUSTOMER_ID = hp.CUSTOMER_ID AND h.CUSTOMER_TYPE = hp.CUSTOMER_TYPE
    WHERE hp.CUSTOMER_ID = p_customer_id;

    -- Recent invoices (auto + home combined)
    SELECT 'AUTO' AS SOURCE, INVOICE_ID, INVOICE_DATE, DUE_DATE, INVOICE_AMOUNT
    FROM JKP_AUTO_INVOICE WHERE CUSTOMER_ID = p_customer_id
    UNION ALL
    SELECT 'HOME' AS SOURCE, INVOICE_ID, INVOICE_DATE, DUE_DATE, INVOICE_AMOUNT
    FROM JKP_HOME_INVOICE WHERE CUSTOMER_ID = p_customer_id
    ORDER BY INVOICE_DATE DESC;
END //

-- SP2: Create a new auto policy with invoice (transaction)
CREATE PROCEDURE sp_create_auto_policy(
    IN p_customer_id SMALLINT,
    IN p_policy_id INT,
    IN p_start_date DATETIME,
    IN p_end_date DATETIME,
    IN p_amount DECIMAL(7,2),
    IN p_invoice_id INT
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Transaction failed. Rolled back.';
    END;

    START TRANSACTION;

    INSERT INTO JKP_AUTO_POLICY (CUSTOMER_ID, CUSTOMER_TYPE, AUTO_POLICY_ID, AUTO_START_DATE, AUTO_END_DATE, AUTO_AMOUNT, AUTO_STATUS)
    VALUES (p_customer_id, 'A', p_policy_id, p_start_date, p_end_date, p_amount, 'C');

    INSERT INTO JKP_AUTO_INVOICE (INVOICE_ID, INVOICE_DATE, DUE_DATE, INVOICE_AMOUNT, CUSTOMER_ID, CUSTOMER_TYPE)
    VALUES (p_invoice_id, p_start_date, DATE_ADD(p_start_date, INTERVAL 30 DAY), p_amount, p_customer_id, 'A');

    COMMIT;
END //

-- SP3: Create a new home policy with invoice (transaction)
CREATE PROCEDURE sp_create_home_policy(
    IN p_customer_id SMALLINT,
    IN p_policy_id BIGINT,
    IN p_start_date DATETIME,
    IN p_end_date DATETIME,
    IN p_amount DECIMAL(10,2),
    IN p_invoice_id INT
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Transaction failed. Rolled back.';
    END;

    START TRANSACTION;

    INSERT INTO JKP_HOME_POLICY (CUSTOMER_ID, CUSTOMER_TYPE, HOME_POLICY_ID, HOME_START_DATE, HOME_END_DATE, HOME_AMOUNT, HOME_STATUS)
    VALUES (p_customer_id, 'H', p_policy_id, p_start_date, p_end_date, p_amount, 'C');

    INSERT INTO JKP_HOME_INVOICE (INVOICE_ID, INVOICE_DATE, DUE_DATE, INVOICE_AMOUNT, CUSTOMER_ID, CUSTOMER_TYPE)
    VALUES (p_invoice_id, p_start_date, DATE_ADD(p_start_date, INTERVAL 30 DAY), p_amount, p_customer_id, 'H');

    COMMIT;
END //

-- SP4: Record a payment (transaction)
CREATE PROCEDURE sp_make_payment(
    IN p_payment_id INT,
    IN p_payment_type VARCHAR(1),
    IN p_invoice_id INT,
    IN p_source VARCHAR(4)  -- 'AUTO' or 'HOME'
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payment transaction failed.';
    END;

    START TRANSACTION;

    IF p_source = 'AUTO' THEN
        INSERT INTO JKP_AUTO_PAYMENT (PAYMENT_ID, PAYMENT_DATE, PAYMENT_TYPE, INVOICE_ID)
        VALUES (p_payment_id, NOW(), p_payment_type, p_invoice_id);
    ELSE
        INSERT INTO JKP_HOME_PAYMENT (PAYMENT_ID, PAYMENT_DATE, PAYMENT_TYPE, INVOICE_ID)
        VALUES (p_payment_id, NOW(), p_payment_type, p_invoice_id);
    END IF;

    COMMIT;
END //

DELIMITER ;
