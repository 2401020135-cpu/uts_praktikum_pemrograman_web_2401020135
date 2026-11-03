<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PDO;
use Throwable;

class PeminjamanController extends Controller
{
    private const PESAN_BACA_GAGAL = 'Data tidak dapat dibaca. Periksa koneksi dan query.';

    // ---------- READ: indeks + pencarian + filter ----------
    public function index(Request $request)
    {
        $kataKunci  = $this->teksQuery($request, 'q');
        $filterAlat = $this->angkaQuery($request, 'alat_id');

        try {
            $pdo = DB::connection()->getPdo();

            $sql = 'SELECT p.id, p.kode_peminjaman, p.nama_peminjam,
                           p.nomor_identitas, p.jumlah_alat, a.nama_alat
                    FROM peminjaman_alat AS p
                    JOIN alat AS a ON a.id = p.alat_id
                    WHERE 1 = 1';
            $parameter = [];

            if ($kataKunci !== '') {
                // Placeholder harus unik (q1, q2, q3): PDO native prepare
                // tidak mengizinkan satu nama dipakai berulang.
                $sql .= ' AND (p.kode_peminjaman LIKE :q1
                            OR p.nama_peminjam LIKE :q2
                            OR p.nomor_identitas LIKE :q3)';
                $pola = '%' . addcslashes($kataKunci, '\\%_') . '%';
                $parameter += ['q1' => $pola, 'q2' => $pola, 'q3' => $pola];
            }

            if ($filterAlat !== null) {
                $sql .= ' AND p.alat_id = :alat_id';
                $parameter['alat_id'] = $filterAlat;
            }

            $sql .= ' ORDER BY p.kode_peminjaman';

            $pernyataan = $pdo->prepare($sql);
            $pernyataan->execute($parameter);
            $daftarPeminjaman = $pernyataan->fetchAll(PDO::FETCH_ASSOC);
            $daftarAlat       = $this->ambilDaftarAlat($pdo);

            return view('peminjaman.index', compact(
                'daftarPeminjaman', 'daftarAlat', 'kataKunci', 'filterAlat'
            ));
        } catch (Throwable $error) {
            report($error);

            return response(self::PESAN_BACA_GAGAL, 500);
        }
    }

    // ---------- CREATE ----------
    public function store(Request $request): RedirectResponse
    {
        // Di luar try: ValidationException adalah Throwable,
        // kalau di dalam try akan tertelan oleh catch.
        $data = $this->validasiInput($request);

        try {
            $pdo   = DB::connection()->getPdo();
            $galat = $this->periksaAturanBisnis($pdo, $data);

            if ($galat !== []) {
                return back()->withInput()->withErrors($galat);
            }

            $simpan = $pdo->prepare(
                'INSERT INTO peminjaman_alat
                    (kode_peminjaman, nama_peminjam, nomor_identitas, jumlah_alat, alat_id)
                 VALUES (:kode, :nama, :nomor, :jumlah, :alat_id)'
            );
            $simpan->execute($this->parameterTulis($data));

            return redirect()->route('peminjaman.index')
                ->with('berhasil', 'Data peminjaman berhasil ditambahkan.');
        } catch (Throwable $error) {
            return $this->tanganiGalat($error, 'Data gagal disimpan. Periksa koneksi dan data.');
        }
    }

    // ---------- UPDATE: form edit ----------
    public function edit(int $id)
    {
        try {
            $pdo   = DB::connection()->getPdo();
            $baris = $this->ambilSatu($pdo, $id);

            if ($baris === null) {
                return redirect()->route('peminjaman.index')
                    ->with('gagal', 'Data tidak ditemukan.');
            }

            $daftarAlat = $this->ambilDaftarAlat($pdo);

            return view('peminjaman.edit', compact('baris', 'daftarAlat'));
        } catch (Throwable $error) {
            report($error);

            return response(self::PESAN_BACA_GAGAL, 500);
        }
    }

    // ---------- UPDATE: simpan perubahan ----------
    public function update(Request $request, int $id): RedirectResponse
    {
        $data = $this->validasiInput($request);

        try {
            $pdo = DB::connection()->getPdo();

            if ($this->ambilSatu($pdo, $id) === null) {
                return redirect()->route('peminjaman.index')
                    ->with('gagal', 'Data tidak ditemukan.');
            }

            // $id dikecualikan agar data tidak "bentrok" dengan dirinya sendiri
            $galat = $this->periksaAturanBisnis($pdo, $data, $id);

            if ($galat !== []) {
                return back()->withInput()->withErrors($galat);
            }

            $ubah = $pdo->prepare(
                'UPDATE peminjaman_alat
                 SET kode_peminjaman = :kode, nama_peminjam = :nama,
                     nomor_identitas = :nomor, jumlah_alat = :jumlah,
                     alat_id = :alat_id
                 WHERE id = :id'
            );
            $ubah->execute($this->parameterTulis($data) + ['id' => $id]);

            return redirect()->route('peminjaman.index')
                ->with('berhasil', 'Data peminjaman berhasil diperbarui.');
        } catch (Throwable $error) {
            return $this->tanganiGalat($error, 'Data gagal diperbarui. Periksa koneksi dan data.');
        }
    }

    // ---------- DELETE ----------
    public function destroy(int $id): RedirectResponse
    {
        try {
            $pdo   = DB::connection()->getPdo();
            $hapus = $pdo->prepare('DELETE FROM peminjaman_alat WHERE id = :id');
            $hapus->execute(['id' => $id]);

            if ($hapus->rowCount() === 0) {
                return redirect()->route('peminjaman.index')
                    ->with('gagal', 'Data tidak ditemukan.');
            }

            return redirect()->route('peminjaman.index')
                ->with('berhasil', 'Data peminjaman berhasil dihapus.');
        } catch (Throwable $error) {
            report($error);

            return redirect()->route('peminjaman.index')
                ->with('gagal', 'Data gagal dihapus. Periksa koneksi dan query.');
        }
    }

    // ================= Helper privat =================

    /** Sanitasi (trim + strip_tags) lalu validasi server-side. */
    private function validasiInput(Request $request): array
    {
        foreach (['kode_peminjaman', 'nama_peminjam', 'nomor_identitas'] as $kolom) {
            $nilai = $request->input($kolom);
            $request->merge([
                $kolom => is_string($nilai) ? trim(strip_tags($nilai)) : '',
            ]);
        }

        return $request->validate([
            'kode_peminjaman' => ['required', 'regex:/^[A-Za-z0-9]{10}$/'],
            'nama_peminjam'   => ['required', 'string', 'min:3', 'max:100'],
            'nomor_identitas' => ['required', 'string', 'min:5', 'max:30'],
            'jumlah_alat'     => ['required', 'integer', 'between:1,10'],
            'alat_id'         => ['required', 'integer'],
        ], [
            'kode_peminjaman.required' => 'Kode peminjaman wajib diisi.',
            'kode_peminjaman.regex'    => 'Kode peminjaman harus tepat 10 karakter huruf/angka.',
            'nama_peminjam.required'   => 'Nama peminjam wajib diisi.',
            'nama_peminjam.min'        => 'Nama peminjam minimal 3 karakter.',
            'nama_peminjam.max'        => 'Nama peminjam maksimal 100 karakter.',
            'nomor_identitas.required' => 'Nomor identitas wajib diisi.',
            'nomor_identitas.min'      => 'Nomor identitas minimal 5 karakter.',
            'nomor_identitas.max'      => 'Nomor identitas maksimal 30 karakter.',
            'jumlah_alat.required'     => 'Jumlah alat wajib diisi.',
            'jumlah_alat.integer'      => 'Jumlah alat harus berupa bilangan bulat.',
            'jumlah_alat.between'      => 'Jumlah alat harus antara 1 sampai 10.',
            'alat_id.required'         => 'Alat wajib dipilih.',
            'alat_id.integer'          => 'Alat yang dipilih tidak valid.',
        ]);
    }

    /** Cek alat tersedia + duplikat kode/identitas. Mengembalikan array pesan error per field. */
    private function periksaAturanBisnis(PDO $pdo, array $data, ?int $kecualiId = null): array
    {
        $galat = [];

        $cekAlat = $pdo->prepare('SELECT COUNT(*) FROM alat WHERE id = :id');
        $cekAlat->execute(['id' => (int) $data['alat_id']]);
        if ((int) $cekAlat->fetchColumn() === 0) {
            $galat['alat_id'] = 'Alat yang dipilih tidak tersedia.';
        }

        $sql = 'SELECT kode_peminjaman, nomor_identitas FROM peminjaman_alat
                WHERE (kode_peminjaman = :kode OR nomor_identitas = :nomor)';
        $parameter = [
            'kode'  => $data['kode_peminjaman'],
            'nomor' => $data['nomor_identitas'],
        ];

        if ($kecualiId !== null) {
            $sql .= ' AND id <> :id';
            $parameter['id'] = $kecualiId;
        }

        $cekDuplikat = $pdo->prepare($sql);
        $cekDuplikat->execute($parameter);

        foreach ($cekDuplikat->fetchAll(PDO::FETCH_ASSOC) as $baris) {
            if (strcasecmp($baris['kode_peminjaman'], $data['kode_peminjaman']) === 0) {
                $galat['kode_peminjaman'] = 'Kode peminjaman sudah digunakan.';
            }
            if (strcasecmp($baris['nomor_identitas'], $data['nomor_identitas']) === 0) {
                $galat['nomor_identitas'] = 'Nomor identitas sudah digunakan.';
            }
        }

        return $galat;
    }

    private function parameterTulis(array $data): array
    {
        return [
            'kode'    => $data['kode_peminjaman'],
            'nama'    => $data['nama_peminjam'],
            'nomor'   => $data['nomor_identitas'],
            'jumlah'  => (int) $data['jumlah_alat'],
            'alat_id' => (int) $data['alat_id'],
        ];
    }

    private function ambilSatu(PDO $pdo, int $id): ?array
    {
        $pernyataan = $pdo->prepare(
            'SELECT id, kode_peminjaman, nama_peminjam, nomor_identitas, jumlah_alat, alat_id
             FROM peminjaman_alat WHERE id = :id'
        );
        $pernyataan->execute(['id' => $id]);
        $baris = $pernyataan->fetch(PDO::FETCH_ASSOC);

        return $baris === false ? null : $baris;
    }

    private function ambilDaftarAlat(PDO $pdo): array
    {
        $pernyataan = $pdo->prepare('SELECT id, nama_alat FROM alat ORDER BY nama_alat');
        $pernyataan->execute();

        return $pernyataan->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Jaring pengaman: error 1062 (duplicate entry) dari UNIQUE di database. */
    private function tanganiGalat(Throwable $error, string $pesanUmum): RedirectResponse
    {
        if ($error instanceof \PDOException && (int) ($error->errorInfo[1] ?? 0) === 1062) {
            return back()->withInput()->withErrors([
                'kode_peminjaman' => 'Kode peminjaman atau nomor identitas sudah digunakan.',
            ]);
        }

        report($error); // detail teknis hanya ke storage/logs/laravel.log

        return back()->withInput()->with('gagal', $pesanUmum);
    }

    private function teksQuery(Request $request, string $kunci): string
    {
        $nilai = $request->query($kunci);

        return is_string($nilai) ? trim(strip_tags($nilai)) : '';
    }

    private function angkaQuery(Request $request, string $kunci): ?int
    {
        $nilai = $request->query($kunci);

        return (is_string($nilai) && ctype_digit($nilai)) ? (int) $nilai : null;
    }
}