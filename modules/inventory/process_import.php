<?php
require_once '../../includes/config.php';


require_post();
csrf_verify();
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($file, "r");
    
    // প্রথম সারি (হেডার) বাদ দেওয়া
    fgetcsv($handle);
    
    $count = 0;
    $db->begin_transaction();
    try {
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $name = clean_input($data[0]);
            $p_price = (float)$data[1];
            $s_price = (float)$data[2];
            $w_price = (float)$data[3];
            $stock = (float)$data[4];

            $stmt = $db->prepare("INSERT INTO products (name, purchase_price, sale_price, wholesale_price, stock) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sdddd", $name, $p_price, $s_price, $w_price, $stock);
            $stmt->execute();
            $count++;
        }
        $db->commit();
        fclose($handle);
        echo "<script>alert('$count টি পণ্য সফলভাবে ইমপোর্ট হয়েছে'); window.location='list.php';</script>";
    } catch (Exception $e) {
        $db->rollback();
        echo "Error: " . $e->getMessage();
    }
}
?>