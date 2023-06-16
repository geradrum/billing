<?php

if (! function_exists('getMonth')) {
    function getMonth($month): string
    {
        $index = array_search($month, [
            'Enero',
            'Febrero',
            'Marzo',
            'Abril',
            'Mayo',
            'Junio',
            'Julio',
            'Agosto',
            'Septiembre',
            'Octubre',
            'Noviembre',
            'Diciembre',
        ]);
        return str_pad(strval($index + 1), '2', '0', STR_PAD_LEFT);
    }
}

if (! function_exists('makeDirectory')) {

    function makeDirectory($path, $mode = 0755, $recursive = false, $force = false)
    {
        if ($force)
        {
            return @mkdir($path, $mode, $recursive);
        }
        else
        {
            return mkdir($path, $mode, $recursive);
        }
    }

}
