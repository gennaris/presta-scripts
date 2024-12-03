<?php
    
    /*
    This script identifies customers in the PrestaShop database with duplicate or single email entries 
    belonging to the default customer group (id_default_group = 1). It performs the following operations:

    CASE A: Multiple Customers with the Same Email
        - Deletes all customers with no associated carts or orders.
        - Retains only one customer with carts/orders.
        - Updates the retained customer's default group to id = 3.
        - Updates the ps_customer_group table to ensure the customer is assigned only to group id = 3.

    CASE B: Single Customer with the Email
        - Updates the customer's default group to id = 3.
        - Updates the ps_customer_group table to ensure the customer is assigned only to group id = 3.

    The script ensures consistency by handling both ps_customer and ps_customer_group tables appropriately.

    Usage:
        - Place this script in the /script/ directory under the PrestaShop root.
        - Execute it via the command line: "php customer-group-cleanup.php".

    Notes:
        - Real-time logs are printed to the console for progress monitoring.
*/


require_once(dirname(__FILE__) . '/../config/config.inc.php');

// Database connection
$db = Db::getInstance();

// Query to find duplicate emails for customers in group id_default_group = 1
$query = "
    SELECT email, COUNT(id_customer) AS email_count
    FROM " . _DB_PREFIX_ . "customer
    WHERE id_default_group = 1 AND email != ''
    GROUP BY email
    HAVING email_count >= 1";

echo "Fetching duplicate emails...\n";

$duplicateEmails = $db->executeS($query);

if (!$duplicateEmails) {
    echo "No duplicate emails found.\n";
    exit;
}

foreach ($duplicateEmails as $row) {
    $email = $row['email'];
    $emailCount = (int)$row['email_count'];

    echo "Processing email: $email (Count: $emailCount)\n";

    // Fetch all customers with this email
    $customersQuery = "
        SELECT id_customer
        FROM " . _DB_PREFIX_ . "customer
        WHERE email = '" . pSQL($email) . "'";
    $customers = $db->executeS($customersQuery);

    if ($emailCount > 1) {
        // CASE A: Multiple customers with the same email
        echo "CASE A: Multiple entries found for $email\n";

        $keptCustomerId = null;
        foreach ($customers as $customer) {
            $id_customer = $customer['id_customer'];

            // Check if customer has carts or orders
            $hasCartsQuery = "
                SELECT COUNT(*) AS cart_count
                FROM " . _DB_PREFIX_ . "cart
                WHERE id_customer = " . (int)$id_customer;
            $hasOrdersQuery = "
                SELECT COUNT(*) AS order_count
                FROM " . _DB_PREFIX_ . "orders
                WHERE id_customer = " . (int)$id_customer;

            $cartCount = $db->getValue($hasCartsQuery);
            $orderCount = $db->getValue($hasOrdersQuery);

            if ($cartCount == 0 && $orderCount == 0) {
                // Delete customer without carts/orders
                echo "Deleting duplicate customer ID $id_customer (No carts/orders).\n";
                $db->delete('customer', 'id_customer = ' . (int)$id_customer);
                $db->delete('customer_group', 'id_customer = ' . (int)$id_customer);
            } else {
                // Keep one customer with carts/orders
                echo "Keeping customer ID $id_customer (Has carts/orders).\n";
                $keptCustomerId = $id_customer;
            }
        }

        if ($keptCustomerId) {
            // Update kept customer to default group id = 3
            echo "Updating customer ID $keptCustomerId to group ID 3.\n";
            $db->update(
                'customer',
                ['id_default_group' => 3],
                'id_customer = ' . (int)$keptCustomerId
            );

            // Update ps_customer_group
            $db->delete('customer_group', 'id_customer = ' . (int)$keptCustomerId);
            $db->insert('customer_group', [
                'id_customer' => $keptCustomerId,
                'id_group' => 3
            ]);
        }
    } else {
        // CASE B: Single entry email
        echo "CASE B: Single entry for $email\n";

        foreach ($customers as $customer) {
            $id_customer = $customer['id_customer'];

            // Update customer to default group id = 3
            echo "Updating customer ID $id_customer to group ID 3.\n";
            $db->update(
                'customer',
                ['id_default_group' => 3],
                'id_customer = ' . (int)$id_customer
            );

            // Update ps_customer_group
            $db->delete('customer_group', 'id_customer = ' . (int)$id_customer);
            $db->insert('customer_group', [
                'id_customer' => $id_customer,
                'id_group' => 3
            ]);
        }
    }
}

echo "Customer email cleanup and group update completed.\n";
