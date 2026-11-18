<?php 
require_once '../../includes/config.php';
include '../../partials/header.php'; 
?>
<div class="card" style="margin: 15px; border-radius:15px;">
    <h3 style="color: var(--dark-blue);">📤 এক্সেল (CSV) থেকে পণ্য ইমপোর্ট</h3>
    <p style="font-size:12px; color:#666;">ফাইল ফরম্যাট: Name, PurchasePrice, SalePrice, WholesalePrice, Stock</p>
    
    <form action="process_import.php" method="POST" enctype="multipart/form-data">
        <div style="border: 2px dashed var(--dark-blue); padding: 30px; text-align:center; border-radius:10px; background:#f0f4ff;">
            <input type="file" name="csv_file" accept=".csv" required><br><br>
            <button type="submit" class="btn-success">আপলোড এবং ইমপোর্ট করুন</button>
        </div>
    </form>
    
    <div style="margin-top:20px; background:#fff3e0; padding:10px; border-radius:8px; font-size:12px;">
        ⚠️ সতর্কতা: শুধুমাত্র .csv ফাইল আপলোড করুন। ফাইলের প্রথম সারি হেডারের জন্য রাখুন।
    </div>
</div>
<?php include '../../partials/footer.php'; ?>