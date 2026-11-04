<?php

declare(strict_types=1);

return [
    'resource' => [
        'label' => 'escritorio',
        'plural_label' => 'Escritorios',
    ],
    'fields' => [
        'name' => 'Nombre',
        'slug' => 'Slug',
        'show_in_navigation' => 'Agregar a la navegación',
        'navigation_icon' => 'Ícono en la navegación',
        'navigation_label' => 'Nombre en la navegación',
        'navigation_label_helper' => 'Déjalo vacío para usar el nombre del escritorio.',
        'navigation_group' => 'Grupo en la navegación',
        'navigation_position' => 'Posición en la navegación',
        'kind' => 'Tipo',
        'owner' => 'Dueño',
        'widget' => 'Widget',
        'width' => 'Ancho',
        'shares' => 'Compartido con',
        'shareable_type' => 'Tipo',
        'shareable' => 'Quién',
        'level' => 'Acceso',
    ],
    'kinds' => [
        'mine' => 'Míos',
        'shared' => 'Compartidos conmigo',
        'system' => 'Del sistema',
    ],
    'navigation_positions' => [
        'start' => 'Al inicio del grupo',
        'end' => 'Al final del grupo',
    ],
    'share_levels' => [
        'read' => 'Ver',
        'edit' => 'Editar',
    ],
    'shareable_types' => [
        'user' => 'Usuario',
        'role' => 'Rol',
    ],
    'widths' => [
        'quarter' => 'Un cuarto',
        'third' => 'Un tercio',
        'half' => 'La mitad',
        'two_thirds' => 'Dos tercios',
        'full' => 'Completo',
    ],
    'actions' => [
        'add_widget' => 'Agregar widget',
        'share' => 'Compartir',
        'settings' => 'Configurar',
        'clone' => 'Clonar',
        'set_home' => 'Usar como inicio',
        'delete' => 'Eliminar',
        'edit_layout' => 'Editar diseño',
        'done' => 'Listo',
        'remove' => 'Quitar',
        'move_up' => 'Subir',
        'move_down' => 'Bajar',
        'resize' => 'Cambiar ancho',
    ],
    'modals' => [
        'add_widget' => [
            'heading' => 'Agregar widget',
            'description' => 'Los widgets son tarjetas con cifras, gráficas o listas.',
        ],
        'share' => [
            'heading' => 'Compartir',
            'description' => 'Elige quién puede ver este escritorio y quién puede editarlo. Solo tú puedes eliminarlo.',
            'add' => 'Agregar',
        ],
        'clone' => [
            'heading' => 'Clonar escritorio',
            'description' => 'Crea una copia editable de este escritorio que solo tú puedes ver.',
            'name' => 'Copia de :name',
        ],
    ],
    'notifications' => [
        'home_set' => 'Este escritorio ahora es tu página de inicio.',
        'read_only' => 'Puedes ver este escritorio. Clónalo para tener tu propia copia.',
    ],
    'empty' => [
        'heading' => 'Aún no hay widgets',
        'description' => 'Agrega widgets para empezar a armar este escritorio.',
    ],
    'validation' => [
        'slug_taken' => 'Otro escritorio ya usa este slug.',
    ],
];
