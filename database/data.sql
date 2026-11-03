USE peminjaman_alat_2401020135;

INSERT INTO alat (nama_alat) VALUES ('Kamera'), ('Proyektor');

-- Subquery dipakai agar tidak bergantung pada nilai id AUTO_INCREMENT
INSERT INTO peminjaman_alat
    (kode_peminjaman, nama_peminjam, nomor_identitas, jumlah_alat, alat_id)
VALUES
    ('PM00000001', 'Rina Kurnia',  'MHS-2201001', 2,
        (SELECT id FROM alat WHERE nama_alat = 'Kamera')),
    ('PM00000002', 'Dedi Pranata', 'MHS-2201002', 1,
        (SELECT id FROM alat WHERE nama_alat = 'Proyektor')),
    ('PM00000003', 'Mira Ananda',  'MHS-2201003', 3,
        (SELECT id FROM alat WHERE nama_alat = 'Kamera'));