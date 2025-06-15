<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Koneksi Database
define('DB_HOST','localhost');
define('DB_USER','root');
define('DB_PASS','');
define('DB_NAME','ims');
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) die('Koneksi gagal: '.$conn->connect_error);

if ($_SERVER['REQUEST_METHOD']==='POST'){
  foreach($_POST['nama_peralatan_utama_standar'] as $i => $nama){
    $fields = ['no_inventaris_dbr','spesifikasi','besaran_yang_dikalibrasi','lab','lab_aggregate',
               'tanggal_terakhir_dikalibrasi','bulan_terakhir_dikalibrasi','instansi_yang_mengkalibrasi',
               'periode_kalibrasi','jadwal_kalibrasi_selanjutnya','realisasi','bulan_realisasi',
               'od_calibrate','estimate_to_calibrate','klasifikasi_estimasi','harga','status',
               'ket','link_tanda_terima_alat','invoice','link_sertifikat_kalibrasi','nomor_tta','status_bayar_invoice','foto'];
    $vals = [$nama];
    foreach($fields as $f) $vals[]= $_POST[$f][$i] ?? null;
    $cols = 'nama_peralatan_utama_standar,'.implode(',',$fields);
    $ph = implode(',', array_fill(0, count($vals), '?'));
    $sql = "INSERT INTO kalibrasi ($cols) VALUES ($ph)";
    $stmt = $conn->prepare($sql);
    $types = str_repeat('s', count($vals));
    $stmt->bind_param($types, ...$vals);
    $stmt->execute();
    $stmt->close();
  }
  echo "<script>alert('Data tersimpan!');location.href='".$_SERVER['PHP_SELF']."';</script>";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Pengajuan Kalibrasi</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">
  <form method="POST" class="w-full max-w-7xl mx-auto bg-white p-6 rounded shadow space-y-6">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <div>
        <label class="block font-semibold mb-1">Dari</label>
        <input type="text" name="nama_pengajuan" required class="w-full p-2 border rounded" placeholder="Kortek/MT Lab...">
      </div>
      <div>
        <label class="block font-semibold mb-1">Tanggal</label>
        <input type="date" name="tanggal_pengajuan" required class="w-full p-2 border rounded">
      </div>
    </div>

    <div id="itemsContainer" class="space-y-6">
      <template id="itemTpl">
        <fieldset class="border pt-6 p-4 rounded-md relative grid grid-cols-1 md:grid-cols-3 gap-4">
          <legend class="absolute top-0 left-4 px-2 bg-white font-semibold">Item</legend>
          <button type="button" class="remove-btn absolute top-2 right-2 text-red-600 hover:text-red-800">✖</button>
          <!-- Kolom 1 -->
          <div class="space-y-4">
            <div><label class="block mb-1">Nama Peralatan</label><input type="text" name="nama_peralatan_utama_standar[]" required class="w-full p-2 border rounded"></div>
            <div><label class="block mb-1">No. Inventaris</label><input type="text" name="no_inventaris_dbr[]" class="w-full p-2 border rounded"></div>
            <div><label class="block mb-1">Spesifikasi</label><input type="text" name="spesifikasi[]" required class="w-full p-2 border rounded"></div>
          </div>
          <!-- Kolom 2 -->
          <div class="space-y-4">
            <div><label class="block mb-1">Besaran Dikalibrasi</label><input type="text" name="besaran_yang_dikalibrasi[]" class="w-full p-2 border rounded"></div>
            <div><label class="block mb-1">Lab</label><input type="text" name="lab[]" required class="w-full p-2 border rounded"></div>
            <div><label class="block mb-1">Lab Aggregate</label><input type="text" name="lab_aggregate[]" class="w-full p-2 border rounded"></div>
          </div>
          <!-- Kolom 3 -->
          <div class="space-y-4">
            <div><label class="block mb-1">Tgl. Terakhir</label><input type="date" name="tanggal_terakhir_dikalibrasi[]" class="w-full p-2 border rounded"></div>
            <div><label class="block mb-1">Bln. Terakhir</label><input type="text" name="bulan_terakhir_dikalibrasi[]" class="w-full p-2 border rounded"></div>
            <div><label class="block mb-1">Instansi</label><input type="text" name="instansi_yang_mengkalibrasi[]" class="w-full p-2 border rounded"></div>
          </div>
          <!-- Baris Bawah: melintang ketiga kolom -->
          <div class="md:col-span-3 grid grid-cols-1 lg:grid-cols-4 gap-4">
            <div><label class="block mb-1">Periode</label><input type="text" name="periode_kalibrasi[]" class="w-full p-2 border rounded"></div>
            <div><label class="block mb-1">Jadwal Selanjutnya</label><input type="date" name="jadwal_kalibrasi_selanjutnya[]" class="w-full p-2 border rounded"></div>
            <div><label class="block mb-1">Realisasi</label><input type="text" name="realisasi[]" class="w-full p-2 border rounded"></div>
            <div><label class="block mb-1">Bln. Realisasi</label><input type="text" name="bulan_realisasi[]" class="w-full p-2 border rounded"></div>
          </div>
          <div class="md:col-span-3 grid grid-cols-1 lg:grid-cols-4 gap-4">
            <div><label class="block mb-1">OD Calibrate</label><input type="text" name="od_calibrate[]" class="w-full p-2 border rounded"></div>
            <div><label class="block mb-1">Estimate</label><input type="text" name="estimate_to_calibrate[]" class="w-full p-2 border rounded"></div>
            <div><label class="block mb-1">Klasifikasi</label><input type="text" name="klasifikasi_estimasi[]" class="w-full p-2 border rounded"></div>
            <div><label class="block mb-1">Harga</label><input type="text" name="harga[]" class="w-full p-2 border rounded"></div>
          </div>
          <div class="md:col-span-3 grid grid-cols-1 lg:grid-cols-4 gap-4">
            <div><label class="block mb-1">Status</label><input type="text" name="status[]" class="w-full p-2 border rounded"></div>
            <div><label class="block mb-1">Keterangan</label><input type="text" name="ket[]" class="w-full p-2 border rounded"></div>
            <div><label class="block mb-1">Link Terima</label><input type="url" name="link_tanda_terima_alat[]" class="w-full p-2 border rounded"></div>
            <div><label class="block mb-1">Invoice</label><input type="text" name="invoice[]" class="w-full p-2 border rounded"></div>
          </div>
          <div class="md:col-span-3 grid grid-cols-1 lg:grid-cols-4 gap-4">
            <div><label class="block mb-1">Link Sertifikat</label><input type="url" name="link_sertifikat_kalibrasi[]" class="w-full p-2 border rounded"></div>
            <div><label class="block mb-1">Nomor TTA</label><input type="text" name="nomor_tta[]" class="w-full p-2 border rounded"></div>
            <div><label class="block mb-1">Status Bayar</label><input type="text" name="status_bayar_invoice[]" class="w-full p-2 border rounded"></div>
            <div><label class="block mb-1">Foto</label><input type="text" name="foto[]" class="w-full p-2 border rounded"></div>
          </div>
        </fieldset>
      </template>
      <div id="firstItem"></div>
    </div>

    <div class="flex justify-end gap-4">
      <button type="button" id="addItemBtn" class="bg-green-500 text-white px-4 py-2 rounded">Tambah Item</button>
      <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded">Submit</button>
      <a href="alat_daftar_user.php"><button type="submit" class="bg-red-600 text-white px-6 py-2 rounded">Back</button></a>
    </div>
  </form>

  <script>
    const cont = document.getElementById('itemsContainer');
    const tpl  = document.getElementById('itemTpl').content;
    const first= document.getElementById('firstItem');
    function add(){
      const cl = document.importNode(tpl,true);
      const n  = cont.querySelectorAll('fieldset').length+1;
      cl.querySelector('legend').textContent = 'Item '+n;
      cl.querySelectorAll('input').forEach(i=>i.value='');
      first.before(cl);
    }
    add();
    document.getElementById('addItemBtn').onclick = add;
    cont.addEventListener('click', e=>{
      if(e.target.matches('.remove-btn')){
        if(cont.querySelectorAll('fieldset').length>1) e.target.closest('fieldset').remove();
        cont.querySelectorAll('fieldset').forEach((f,i)=>f.querySelector('legend').textContent='Item '+(i+1));
      }
    });
  </script>
</body>
</html> 