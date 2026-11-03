<?php

if (! function_exists('statusJumlah')) {
    /** Banyak jika jumlah >= 3, selain itu Sedikit. */
    function statusJumlah(int $jumlah): string
    {
        if ($jumlah >= 3) {
            return 'Banyak';
        }

        return 'Sedikit';
    }
}