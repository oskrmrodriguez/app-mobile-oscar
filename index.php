<?php
// Punto de entrada simple para probar que el servidor PHP está funcionando.
header('Content-Type: application/json; charset=utf-8');

echo json_encode([
  'ok'   => true,
  'msg'  => 'API backend para la app móvil en funcionamiento',
  'time' => date('c')
]);
