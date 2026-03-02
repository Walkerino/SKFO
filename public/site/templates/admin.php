<?php namespace ProcessWire;

wire()->addHookAfter('Dashboard::getPanels', function ($event) {
    /* Get list of panels */
    $panels = $event->return;

    // Define regions with their IDs
    $regions = [
        '1215' => 'Республика Дагестан',
        '1216' => 'Республика Ингушетия',
        '1217' => 'Кабардино-Балкарская Республика',
        '1218' => 'Карачаево-Черкесская Республика',
        '1219' => 'Республика Северная Осетия — Алания',
        '1220' => 'Чеченская Республика',
        '1221' => 'Ставропольский край'
    ];

    // Define templates to track
    $templates = ['article', 'place', 'hotel', 'tour', 'car', 'guide'];

    // Build statistics array
    $stats = [];
    foreach ($templates as $template) {
        $stats[$template] = [];
        foreach (array_keys($regions) as $region_id) {
            $count = wire('pages')->count("template=$template, region=$region_id");
            $stats[$template][$region_id] = $count;
        }
    }

    // Prepare chart datasets
    $datasets = [];
    $colors = [
        'rgba(75, 192, 192, 0.6)', // Article
        'rgba(255, 99, 132, 0.6)',  // Place
        'rgba(54, 162, 235, 0.6)',  // Hotel
        'rgba(255, 206, 86, 0.6)',   // Tour
        'rgba(153, 102, 255, 0.6)', // Car
        'rgba(255, 159, 64, 0.6)',  // Guide
    ];

    foreach ($templates as $index => $template) {
        $templateObj = wire('templates')->get($template);
        $templateLabel = $templateObj && $templateObj->label ? $templateObj->label : ucfirst($template); // Use label if available, else capitalize template name
        $datasets[] = [
            'label' => $templateLabel,
            'data' => array_values($stats[$template]),
            'backgroundColor' => $colors[$index],
            'borderColor' => str_replace('0.6', '1', $colors[$index]),
            'borderWidth' => 1
        ];
    }

    // Add chart panel
    $panels->add([
        'size' => 'full',
        'panel' => 'chart',
        'title' => 'Статистика по регионам',
        'data' => [
            'chart' => [
                'type' => 'bar', // Stacked bar chart
                'data' => [
                    'labels' => array_values($regions), // Region names as x-axis labels
                    'datasets' => $datasets
                ],
                'options' => [
                    'aspectRatio' => 2.5,
                    'scales' => [
                        'xAxes' => [
                            ['gridLines' => ['display' => false]]
                        ],
                        'yAxes' => [
                            [
                                'stacked' => true, // Enable stacking
                                'ticks' => [
                                    'beginAtZero' => true,
                                    'stepSize' => 1 // Ensure integer steps for counts
                                ]
                            ]
                        ]
                    ],
                    'plugins' => [
                        'legend' => [
                            'display' => true,
                            'position' => 'top'
                        ]
                    ]
                ]
            ]
        ]
    ]);

    // Preserve existing panels
    $panels->add([
        'size' => 'mini',
        'panel' => 'number',
        'title' => 'Статьи',
        'data' => [
            'number' => wire('pages')->count('template=article'),
            'detail' => 'Всего статей',
            'trend' => 'none'
        ]
    ]);

    $panels->add([
        'size' => 'mini',
        'panel' => 'number',
        'title' => 'Достопримечательности',
        'data' => [
            'number' => wire('pages')->count('template=place'),
            'detail' => 'Всего мест',
            'trend' => 'none'
        ]
    ]);

    $panels->add([
        'size' => 'mini',
        'panel' => 'number',
        'title' => 'Санатории и отели',
        'data' => [
            'number' => wire('pages')->count('template=hotel'),
            'detail' => 'Всего отелей',
            'trend' => 'none'
        ]
    ]);

    $panels->add([
        'size' => 'mini',
        'panel' => 'number',
        'title' => 'Туры',
        'data' => [
            'number' => wire('pages')->count('template=tour'),
            'detail' => 'Всего туров',
            'trend' => 'none'
        ]
    ]);

    $panels->add([
        'size' => 'mini',
        'panel' => 'add-new',
        'title' => 'Новая страница',
        'data' => [
            'display' => 'list',
        ],
    ]);

    $panels->add([
        'size' => 'normal',
        'panel' => 'collection',
        'title' => 'Статьи',
        'data' => [
            'collection' => 'template=article, limit=12, sort=sort',
            'sortable' => true,
            'headers' => false,
            'columns' => ['title', 'region'],
            'actions' => ['view', 'edit', 'trash'],
        ],
    ]);

    $panels->add([
        'size' => 'mini',
        'panel' => 'collection',
        'title' => 'Лендинги',
        'data' => [
            'collection' => 'template=lp, limit=12, include=hidden',
            'sortable' => true,
            'headers' => true,
            'columns' => ['title'],
            'actions' => ['view', 'edit', 'trash'],
        ],
    ]);

    $panels->add([
        'size' => 'full',
        'panel' => 'collection',
        'title' => 'Места',
        'data' => [
            'collection' => 'template=place, limit=10',
            'sortable' => true,
            'headers' => false,
            'columns' => ['title', 'region'],
            'actions' => ['view', 'edit', 'trash'],
        ],
    ]);

    $panels->add([
        'size' => 'full',
        'panel' => 'collection',
        'title' => 'Отели',
        'data' => [
            'collection' => 'template=hotel, limit=10, sort=date',
            'sortable' => true,
            'headers' => true,
            'columns' => ['title', 'hotel_info.type', 'region', 'hotel_info.stars', 'hotel_info.rooms', 'hotel_conditions.check_in', 'hotel_conditions.check_out'],
            'actions' => ['view', 'edit', 'trash'],
        ],
    ]);

    $panels->add([
        'size' => 'full',
        'panel' => 'collection',
        'title' => 'Туры',
        'data' => [
            'collection' => 'template=tour, limit=10',
            'sortable' => true,
            'headers' => true,
            'columns' => ['title', 'tour.qty', 'tour.duration', 'guide'],
            'actions' => ['view', 'edit', 'trash'],
        ],
    ]);

    $panels->add([
        'size' => 'full',
        'panel' => 'collection',
        'title' => 'Машины',
        'data' => [
            'collection' => 'template=car, limit=10',
            'sortable' => true,
            'headers' => true,
            'columns' => ['car_info.type', 'car_info.year', 'car_info.make', 'car_info.model', 'car_info.seats'],
            'actions' => ['view', 'edit', 'trash'],
        ],
    ]);

    $panels->add([
        'size' => 'mini',
        'panel' => 'collection',
        'title' => 'Гиды',
        'data' => [
            'collection' => 'template=guide, limit=10',
            'sortable' => true,
            'headers' => false,
            'columns' => ['title'],
            'actions' => ['view', 'edit', 'trash'],
        ],
    ]);

    $panels->add([
        'size' => 'normal',
        'panel' => 'collection',
        'title' => 'Регионы',
        'data' => [
            'collection' => 'template=region, limit=10',
            'sortable' => true,
            'headers' => false,
            'columns' => ['title'],
            'actions' => ['view', 'edit', 'trash'],
        ],
    ]);

    $panels->add([
        'size' => 'mini',
        'panel' => 'collection',
        'title' => 'Города',
        'data' => [
            'collection' => 'template=city, limit=10',
            'sortable' => true,
            'headers' => false,
            'columns' => ['title'],
            'actions' => ['view', 'edit', 'trash'],
        ],
    ]);

});

wire()->addHookAfter('Dashboard::getSettings', function ($event) {
    $event->return->displayIcons = true;
});

/* Make sure to add the hook *before* the default admin process */
require $config->paths->adminTemplates . 'controller.php';