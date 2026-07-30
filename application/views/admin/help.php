<section class="help-hero">
  <span class="eyebrow">Panduan sederhana</span>
  <h2>Membuat form survei cukup mengikuti tiga langkah.</h2>
  <p>Buka menu “Survei, Form & Shortcut”, tekan “Buat survei baru”, lalu ikuti urutan input awal, identitas, dan pertanyaan. Data baru disimpan setelah langkah terakhir selesai.</p>
  <div style="margin-top:18px"><a class="btn btn-secondary" href="<?= site_url('admin/forms/create') ?>">Mulai buat form survei</a></div>
</section>

<div class="help-steps">
  <section class="help-step-card">
    <div class="help-step-number">1</div>
    <div>
      <span class="help-menu-label">Input awal wajib</span>
      <h3>Pilih data yang harus diisi pertama</h3>
      <p>Pilih email, nomor telepon, nomor identitas/nomor induk, atau buat input sendiri. Semua kolom pada langkah ini wajib diisi sebelum responden dapat melanjutkan.</p>
      <div class="help-note"><b>Contoh penggunaan:</b> survei pelanggan hanya meminta email; survei siswa meminta nomor induk; survei anggota meminta kode anggota buatan sendiri.</div>
    </div>
  </section>

  <section class="help-step-card">
    <div class="help-step-number">2</div>
    <div>
      <span class="help-menu-label">Identitas responden</span>
      <h3>Tentukan data identitas yang diperlukan</h3>
      <p>Nama, alamat, jenis kelamin, usia, pendidikan, pekerjaan, dan layanan dapat dibuat wajib, opsional, atau tidak ditampilkan.</p>
      <div class="help-mode-grid">
        <div><b>Tidak ditampilkan</b><span>Kolom tidak muncul di form.</span></div>
        <div><b>Opsional</b><span>Kolom muncul tetapi boleh dikosongkan.</span></div>
        <div><b>Wajib</b><span>Responden harus mengisinya.</span></div>
      </div>
      <p>Jika pilihan belum tersedia, tekan “Tambah identitas” untuk membuat kolom baru seperti instansi, kelas, nama orang tua, kecamatan, atau jabatan.</p>
    </div>
  </section>

  <section class="help-step-card">
    <div class="help-step-number">3</div>
    <div>
      <span class="help-menu-label">Pertanyaan survei</span>
      <h3>Pilih pertanyaan lama atau buat langsung</h3>
      <p>Pertanyaan yang belum digunakan survei lain dapat dipilih dari daftar. Jika pertanyaan belum ada, buat langsung beserta pengukuran, kategori, bobot, pilihan jawaban, nilai, dan skor.</p>
      <div class="help-note"><b>Perhatian:</b> pertanyaan yang sudah menjadi milik survei lain tetap disembunyikan agar hasil setiap survei tidak tumpang tindih.</div>
    </div>
  </section>

  <section class="help-step-card">
    <div class="help-step-number">✓</div>
    <div>
      <span class="help-menu-label">Form berhasil dibuat</span>
      <h3>Pratinjau, uji, kemudian bagikan shortcut</h3>
      <p>Setelah disimpan, sistem membuat survei, pertanyaan baru, tepat satu form utama, shortcut, dan pengaturan data responden sekaligus. Buka pratinjau untuk memastikan urutan pengisian sudah sesuai.</p>
      <div class="help-action-row">
        <a class="btn btn-secondary btn-sm" href="<?= site_url('admin/forms') ?>">Lihat survei, form & shortcut</a>
        <a class="btn btn-secondary btn-sm" href="<?= site_url('admin/dashboard') ?>">Lihat dashboard</a>
        <a class="btn btn-secondary btn-sm" href="<?= site_url('admin/responses') ?>">Lihat respons</a>
      </div>
    </div>
  </section>
</div>

<section class="panel help-glossary">
  <div class="panel-head"><div><h2>Pengaturan lanjutan</h2><p>Gunakan tombol pada halaman utama jika ingin mengubah data yang sudah dibuat.</p></div></div>
  <dl>
    <div><dt>Pertanyaan & Jawaban</dt><dd>Gunakan filter untuk melihat pertanyaan per survei. Tombol “Tambah pertanyaan” membuka halaman khusus, lalu pilihan jawaban baru ditambahkan melalui modal.</dd></div>
    <div><dt>Survei, Form & Shortcut</dt><dd>Satu survei selalu mempunyai satu form utama. Gabungan beberapa survei dibuat sebagai paket terpisah sehingga tidak menambah jumlah form utama. Pengaturan teknis survei dibuka dari tombol “Atur survei”.</dd></div>
    <div><dt>Data Respons</dt><dd>Melihat identitas, kolom fleksibel, jawaban, dan nilai setiap respons. Pilihan survei, sektor, dan perangkat daerah muncul otomatis hanya jika terdapat pada data.</dd></div>
    <?php if ($admin_role === 'superadmin'): ?>
      <div><dt>API Builder</dt><dd>Membuat endpoint baca khusus untuk aplikasi lain. Setiap peminta mempunyai kunci, cakupan survei, jenis keluaran, kolom detail, batas permintaan, dan catatan penggunaan sendiri. Setelah akses tersimpan, gunakan tombol “Dokumentasi PDF” untuk memperoleh panduan Postman, cURL, contoh kode, kondisi error, serta pengesahan keluaran sistem.</dd></div>
    <?php endif; ?>
  </dl>
</section>

<?php if ($admin_role === 'superadmin'): ?>
<section class="panel">
  <div class="panel-head">
    <div>
      <h2>Panduan singkat API Builder</h2>
      <p>Gunakan fitur ini ketika instansi atau aplikasi lain meminta data survei.</p>
    </div>
    <a class="btn btn-secondary btn-sm" href="<?= site_url('admin/api-builder') ?>">Buka API Builder</a>
  </div>
  <div class="help-mode-grid">
    <div><b>1. Buat peminta</b><span>Isi nama aplikasi dan kode endpoint yang mudah dikenali.</span></div>
    <div><b>2. Batasi data</b><span>Pilih survei, ringkasan, grafik, detail, pertanyaan, serta kolom yang boleh dikirim.</span></div>
    <div><b>3. Salin kredensial</b><span>Berikan endpoint dan kunci satu kali melalui jalur komunikasi yang aman.</span></div>
    <div><b>4. Unduh PDF</b><span>Bagikan dokumentasi teknis resmi; kunci lengkap tetap harus dikirim terpisah.</span></div>
  </div>
  <div class="help-note" style="margin-top:14px">
    <b>Perhatian:</b> kunci lengkap hanya ditampilkan setelah dibuat atau dibuat ulang. Jangan mengirim kunci melalui halaman publik dan segera nonaktifkan akses yang sudah tidak digunakan.
  </div>
</section>
<?php endif; ?>
