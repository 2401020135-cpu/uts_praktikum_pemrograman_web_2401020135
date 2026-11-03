<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Peminjaman</title>
</head>
<body>
    <h1>Edit Peminjaman</h1>

    @if (session('gagal'))
        <p>{{ session('gagal') }}</p>
    @endif
    @if ($errors->any())
        <h2>Data belum valid</h2>
        <ul>
            @foreach ($errors->all() as $pesan)
                <li>{{ $pesan }}</li>
            @endforeach
        </ul>
    @endif

    <form action="{{ route('peminjaman.update', $baris['id']) }}" method="POST" novalidate>
        @csrf
        @method('PUT')
        <p>Kode Peminjaman<br>
            <input type="text" name="kode_peminjaman"
                   value="{{ old('kode_peminjaman', $baris['kode_peminjaman']) }}"></p>
        <p>Nama Peminjam<br>
            <input type="text" name="nama_peminjam"
                   value="{{ old('nama_peminjam', $baris['nama_peminjam']) }}"></p>
        <p>Nomor Identitas<br>
            <input type="text" name="nomor_identitas"
                   value="{{ old('nomor_identitas', $baris['nomor_identitas']) }}"></p>
        <p>Jumlah Alat<br>
            <input type="number" name="jumlah_alat"
                   value="{{ old('jumlah_alat', $baris['jumlah_alat']) }}"></p>
        <p>Alat<br>
            <select name="alat_id">
                <option value="">-- Pilih Alat --</option>
                @foreach ($daftarAlat as $alat)
                    <option value="{{ $alat['id'] }}"
                        @selected((string) old('alat_id', $baris['alat_id']) === (string) $alat['id'])>
                        {{ $alat['nama_alat'] }}
                    </option>
                @endforeach
            </select>
        </p>
        <button type="submit">Simpan Perubahan</button>
        <a href="{{ route('peminjaman.index') }}">Batal</a>
    </form>
</body>
</html>