<?php
// Definir la URL base
define('BASE_URL', 'https://pesdb.net/efootball/?mode=authentic&all=1&featured=0&sort=id&page=');

// Obtener parámetros
$current_page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
$search_query = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING);

// Función mejorada para obtener datos y paginación
function fetch_paginated_data($page, $search_query = '') {
    $url = BASE_URL . $page . ($search_query ? "&search=" . urlencode($search_query) : "");

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0',
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 15
    ]);

    $html = curl_exec($ch);
    if (!$html) {
        return ['error' => 'Error al cargar los datos: ' . curl_error($ch)];
    }
    curl_close($ch);

    // Procesar HTML
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);

    // Extraer datos de la tabla
    $table = $xpath->query('//table[contains(@class, "players")]')->item(0);
    if (!$table) {
        return ['error' => 'Tabla no encontrada'];
    }

    $rows = $table->getElementsByTagName('tr');
    $players = [];

    foreach ($rows as $row) {
        $cols = $row->getElementsByTagName('td');
        if ($cols->length >= 9) {
            $players[] = [
                'id' => $cols->item(0)->nodeValue,
                'position' => $cols->item(1)->nodeValue,
                'player_name' => $cols->item(2)->nodeValue,
                'team_name' => $cols->item(3)->nodeValue,
                'nationality' => $cols->item(4)->nodeValue,
                'height' => $cols->item(5)->nodeValue,
                'weight' => $cols->item(6)->nodeValue,
                'age' => $cols->item(7)->nodeValue,
                'overall_rating' => $cols->item(8)->nodeValue
            ];
        }
    }

    // Detectar total de páginas
    $pagination_links = $xpath->query('//div[@class="pages"]/a');
    $total_pages = 1;

    if ($pagination_links->length > 0) {
        // El penúltimo enlace suele ser el último número de página
        $last_page = $pagination_links->item($pagination_links->length - 2)->nodeValue;
        $total_pages = intval($last_page) ?: 1;
    }

    return [
        'players' => $players,
        'current_page' => $page,
        'total_pages' => $total_pages,
        'search_query' => $search_query
    ];
}

// Obtener datos
$result = fetch_paginated_data($current_page, $search_query);

// Calcular el rango de páginas visibles
$pages_per_group = 10; // Número de páginas por grupo
$current_group = ceil($current_page / $pages_per_group); // Grupo actual
$start_page = ($current_group - 1) * $pages_per_group + 1; // Primera página del grupo
$end_page = min($start_page + $pages_per_group - 1, $result['total_pages']); // Última página del grupo
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jugadores de eFootball</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .pagination .page-item.active .page-link {
            background-color: #007bff;
            border-color: #007bff;
        }
        .search-box {
            max-width: 400px;
            margin: 20px auto;
        }
    </style>
</head>
<body>
<div class="container">
    <h2 class="text-center my-4">Base de Datos de Jugadores</h2>

    <!-- Buscador -->
    <form method="GET" class="search-box">
        <div class="input-group">
            <input type="text" name="search" class="form-control" 
                   placeholder="Buscar jugador..." value="<?= htmlspecialchars($search_query ?? '') ?>">
            <button class="btn btn-primary" type="submit">Buscar</button>
        </div>
    </form>

    <!-- Tabla de resultados -->
    <?php if (isset($result['error'])): ?>
        <div class="alert alert-danger"><?= $result['error'] ?></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Posición</th>
                        <th>Nombre</th>
                        <th>Equipo</th>
                        <th>Nacionalidad</th>
                        <th>Altura</th>
                        <th>Peso</th>
                        <th>Edad</th>
                        <th>Rating</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($result['players'] as $player): ?>
                        <tr>
                            <?php foreach ($player as $value): ?>
                                <td><?= htmlspecialchars(trim($value)) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        <?php if ($result['total_pages'] > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <!-- Botón "Anterior" -->
                    <?php if ($current_page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" 
                               href="?page=<?= $current_page - 1 ?>&search=<?= urlencode($result['search_query']) ?>">
                                Anterior
                            </a>
                        </li>
                    <?php endif; ?>

                    <!-- Enlaces de páginas -->
                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <li class="page-item <?= $i === $current_page ? 'active' : '' ?>">
                            <a class="page-link" 
                               href="?page=<?= $i ?>&search=<?= urlencode($result['search_query']) ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>

                    <!-- Botón "Siguiente" -->
                    <?php if ($current_page < $result['total_pages']): ?>
                        <li class="page-item">
                            <a class="page-link" 
                               href="?page=<?= $current_page + 1 ?>&search=<?= urlencode($result['search_query']) ?>">
                                Siguiente
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
