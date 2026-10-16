<?php

/**
 * What can be imported and which fields each record has. The import screens, the column mapper, the validation
 * and the blank templates are all built from this list.
 *
 * type: string | text | decimal | int | bool | date | enum | ref (a lookup to another record) | mark (engraving mark)
 * aliases: other names a column may carry, used to map columns automatically.
 */
$status = ['ACTIVE', 'INACTIVE', 'RETIRED'];

return [
    'max_rows' => 20000,
    'max_columns' => 200,
    'max_upload_kb' => 20480,
    'preview_rows' => 100,
    'commit_seconds' => 20,   // each request writes for about this long, then the progress page carries on

    'entities' => [
        'product' => [
            'label' => 'Products', 'model' => \App\Models\Product::class, 'table' => 'products', 'kind' => 'product',
            'blurb' => 'Castings, cores and other items we make or sell. An optional revision column adds the first revision too.',
            'virtual' => ['revision', 'revision_status'],
            'revisions' => ['model' => \App\Models\ProductRevision::class, 'owner_key' => 'product_id', 'copy' => ['net_weight_kg' => 'nominal_weight_kg']],
            'match' => ['code' => 'Code', 'name' => 'Name'],
            'fields' => [
                'code' => ['label' => 'Code', 'type' => 'string', 'max' => 40, 'aliases' => ['product code', 'part number', 'part no', 'item code', 'sku', 'id', 'item no'], 'help' => 'Leave blank to have the system make one.'],
                'name' => ['label' => 'Name', 'type' => 'string', 'max' => 255, 'required' => true, 'aliases' => ['product name', 'description', 'part name', 'item name', 'title']],
                'product_type' => ['label' => 'Type', 'type' => 'enum', 'enum' => ['CASTING', 'CORE', 'OTHER'], 'default' => 'CASTING', 'aliases' => ['product type', 'category', 'kind']],
                'base_uom' => ['label' => 'Unit of measure', 'type' => 'string', 'max' => 20, 'default' => 'piece', 'aliases' => ['uom', 'unit', 'units']],
                'nominal_weight_kg' => ['label' => 'Nominal weight (kg)', 'type' => 'decimal', 'scale' => 4, 'min' => 0, 'aliases' => ['weight', 'weight kg', 'casting weight', 'net weight', 'unit weight']],
                'description' => ['label' => 'Description', 'type' => 'text', 'aliases' => ['details', 'remarks', 'comment', 'comments']],
                'active' => ['label' => 'Active', 'type' => 'bool', 'default' => '1', 'aliases' => ['is active', 'status', 'enabled']],
                'revision' => ['label' => 'Revision', 'type' => 'string', 'max' => 20, 'aliases' => ['rev', 'revision letter', 'drawing revision', 'issue'], 'help' => 'Adds this revision to the product when it does not have it yet.'],
                'revision_status' => ['label' => 'Revision status', 'type' => 'enum', 'enum' => ['DRAFT', 'APPROVED', 'OBSOLETE'], 'default' => 'DRAFT', 'aliases' => ['rev status'], 'help' => 'Used only for a revision this import adds. Imported history is often APPROVED.'],
            ],
        ],
        'pattern' => [
            'label' => 'Patterns', 'model' => \App\Models\Pattern::class, 'table' => 'patterns', 'kind' => 'pattern', 'tool' => true,
            'blurb' => 'Matchplates, cope and drag sets and loose patterns, with their engraved mark. An optional revision column adds the first revision too.',
            'virtual' => ['revision', 'revision_status'],
            'revisions' => ['model' => \App\Models\PatternRevision::class, 'owner_key' => 'pattern_id'],
            'match' => ['code' => 'Code', 'name' => 'Name', 'mark_code' => 'Engraving mark'],
            'fields' => [
                'code' => ['label' => 'Code', 'type' => 'string', 'max' => 40, 'aliases' => ['pattern code', 'pattern no', 'pattern number', 'id', 'tool code'], 'help' => 'Leave blank to have the system make one.'],
                'mark_code' => ['label' => 'Engraving mark', 'type' => 'mark', 'aliases' => ['mark', 'engraved mark', 'engraving', 'stamp', 'tool mark'], 'help' => 'The mark already engraved on an old tool. Blank: the next free mark is issued.'],
                'name' => ['label' => 'Name', 'type' => 'string', 'max' => 255, 'required' => true, 'aliases' => ['pattern name', 'description', 'title']],
                'pattern_type' => ['label' => 'Pattern type', 'type' => 'enum', 'enum' => ['MATCHPLATE', 'COPE_DRAG', 'LOOSE_PATTERN', 'SPLIT_PATTERN', 'OTHER'], 'default' => 'MATCHPLATE', 'aliases' => ['type', 'kind', 'plate type']],
                'status' => ['label' => 'Status', 'type' => 'enum', 'enum' => $status, 'default' => 'ACTIVE', 'aliases' => ['state', 'condition']],
                'sand_system' => ['label' => 'Made for sand', 'type' => 'enum', 'enum_from' => \App\Models\SandSystem::class, 'aliases' => ['sand', 'sand system', 'made for sand', 'moulding sand']],
                'material' => ['label' => 'Material', 'type' => 'string', 'max' => 50, 'aliases' => ['pattern material', 'made of']],
                'length_mm' => ['label' => 'Length (mm)', 'type' => 'decimal', 'scale' => 2, 'min' => 0, 'aliases' => ['length', 'l', 'l mm']],
                'width_mm' => ['label' => 'Width (mm)', 'type' => 'decimal', 'scale' => 2, 'min' => 0, 'aliases' => ['width', 'w', 'w mm', 'breadth']],
                'height_mm' => ['label' => 'Height (mm)', 'type' => 'decimal', 'scale' => 2, 'min' => 0, 'aliases' => ['height', 'h', 'h mm', 'thickness']],
                'description' => ['label' => 'Description', 'type' => 'text', 'aliases' => ['details']],
                'notes' => ['label' => 'Notes', 'type' => 'text', 'aliases' => ['remarks', 'comment', 'comments']],
                'revision' => ['label' => 'Revision', 'type' => 'string', 'max' => 20, 'aliases' => ['rev', 'pattern revision', 'pattern rev', 'issue'], 'help' => 'Adds this revision to the pattern when it does not have it yet.'],
                'revision_status' => ['label' => 'Revision status', 'type' => 'enum', 'enum' => ['DRAFT', 'APPROVED', 'OBSOLETE'], 'default' => 'DRAFT', 'aliases' => ['rev status'], 'help' => 'Used only for a revision this import adds. Patterns already in use are often APPROVED.'],
            ],
        ],
        'pattern_cavity' => [
            'label' => 'Pattern cavities', 'kind' => 'pattern_cavity', 'handler' => \App\Services\Import\PatternCavityRows::class,
            'blurb' => 'Which product each cavity of a pattern makes, one row per cavity. Import the patterns and products first.',
            'match' => ['cavity' => 'Pattern, revision and cavity number'],
            'columns' => ['Cavity', 'Makes'],
            'match_fields' => ['cavity' => ['pattern', 'cavity_number']],
            'fields' => [
                'pattern' => ['label' => 'Pattern', 'type' => 'ref', 'required' => true, 'aliases' => ['pattern code', 'pattern no', 'pattern number', 'pattern name', 'tool code', 'mark'], 'help' => 'The pattern\'s code, engraving mark or name.'],
                'pattern_revision' => ['label' => 'Pattern revision', 'type' => 'string', 'max' => 20, 'aliases' => ['pattern rev', 'revision', 'rev'], 'help' => 'Blank: the pattern\'s only revision, or revision A when it has none. A revision it does not have yet is added.'],
                'revision_status' => ['label' => 'Revision status', 'type' => 'enum', 'enum' => ['DRAFT', 'APPROVED', 'OBSOLETE'], 'default' => 'DRAFT', 'aliases' => ['rev status', 'pattern revision status'], 'help' => 'Used only for a pattern revision this import adds.'],
                'cavity_number' => ['label' => 'Cavity number', 'type' => 'int', 'min' => 1, 'max' => 10000, 'required' => true, 'aliases' => ['cavity', 'cavity no', 'cav', 'cav no', 'impression', 'position']],
                'product' => ['label' => 'Product', 'type' => 'ref', 'required' => true, 'aliases' => ['product code', 'part number', 'part no', 'item code', 'casting', 'product name'], 'help' => 'The product\'s code or name.'],
                'product_revision' => ['label' => 'Product revision', 'type' => 'string', 'max' => 20, 'aliases' => ['product rev', 'part rev', 'part revision', 'drawing revision', 'drawing rev'], 'help' => 'Blank: the product\'s only current revision.'],
                'cavity_role' => ['label' => 'Makes', 'type' => 'enum', 'enum' => ['WHOLE', 'PART'], 'default' => 'WHOLE', 'aliases' => ['role', 'cavity role', 'cavity type'], 'help' => 'WHOLE: a complete casting. PART: one component of the product.'],
                'component_label' => ['label' => 'Component', 'type' => 'string', 'max' => 100, 'aliases' => ['component name', 'section'], 'help' => 'Needed when the cavity makes a part.'],
                'pieces_per_product' => ['label' => 'Pieces per product', 'type' => 'int', 'min' => 1, 'max' => 1000, 'default' => '1', 'aliases' => ['pieces', 'pcs per product', 'qty per product', 'pieces per casting']],
                'casting_weight_kg' => ['label' => 'Casting weight (kg)', 'type' => 'decimal', 'scale' => 4, 'min' => 0, 'aliases' => ['casting weight', 'casting kg', 'net weight', 'weight']],
                'poured_weight_kg' => ['label' => 'Poured weight (kg)', 'type' => 'decimal', 'scale' => 4, 'min' => 0, 'aliases' => ['poured weight', 'poured kg', 'pour weight', 'gross weight']],
                'notes' => ['label' => 'Notes', 'type' => 'text', 'max' => 2000, 'aliases' => ['remarks', 'comment', 'comments']],
            ],
        ],
        'core_definition' => [
            'label' => 'Cores', 'model' => \App\Models\CoreDefinition::class, 'table' => 'core_definitions', 'kind' => 'core_definition', 'tool' => true,
            'blurb' => 'The cores themselves. Import these before the core boxes that make them.',
            'match' => ['code' => 'Code', 'name' => 'Name', 'mark_code' => 'Engraving mark'],
            'fields' => [
                'code' => ['label' => 'Code', 'type' => 'string', 'max' => 40, 'aliases' => ['core code', 'core no', 'core number', 'id']],
                'mark_code' => ['label' => 'Engraving mark', 'type' => 'mark', 'aliases' => ['mark', 'engraved mark', 'stamp']],
                'name' => ['label' => 'Name', 'type' => 'string', 'max' => 255, 'required' => true, 'aliases' => ['core name', 'description', 'title']],
                'core_type' => ['label' => 'Core type', 'type' => 'string', 'max' => 30, 'default' => 'STANDARD', 'aliases' => ['type', 'kind']],
                'status' => ['label' => 'Status', 'type' => 'enum', 'enum' => $status, 'default' => 'ACTIVE', 'aliases' => ['state']],
                'nominal_weight_kg' => ['label' => 'Nominal weight (kg)', 'type' => 'decimal', 'scale' => 4, 'min' => 0, 'aliases' => ['weight', 'weight kg', 'core weight']],
                'process_type' => ['label' => 'Core process', 'type' => 'string', 'max' => 30, 'aliases' => ['process', 'binder', 'method']],
                'description' => ['label' => 'Description', 'type' => 'text', 'aliases' => ['details']],
                'engineering_notes' => ['label' => 'Engineering notes', 'type' => 'text', 'aliases' => ['notes', 'remarks', 'comments']],
            ],
        ],
        'core_box' => [
            'label' => 'Core boxes', 'model' => \App\Models\CoreBox::class, 'table' => 'core_boxes', 'kind' => 'core_box', 'tool' => true,
            'blurb' => 'Core boxes, each linked to the core it makes (by the core\'s code or name).',
            'match' => ['code' => 'Code', 'name' => 'Name', 'mark_code' => 'Engraving mark'],
            'fields' => [
                'code' => ['label' => 'Code', 'type' => 'string', 'max' => 40, 'aliases' => ['core box code', 'box code', 'box no', 'core box no', 'id']],
                'mark_code' => ['label' => 'Engraving mark', 'type' => 'mark', 'aliases' => ['mark', 'engraved mark', 'stamp']],
                'name' => ['label' => 'Name', 'type' => 'string', 'max' => 255, 'required' => true, 'aliases' => ['core box name', 'box name', 'description', 'title']],
                'core_definition' => ['label' => 'Core it makes', 'type' => 'ref', 'ref' => 'core_definition', 'required' => true, 'aliases' => ['core', 'core code', 'core name', 'core definition', 'makes core']],
                'revision' => ['label' => 'Revision', 'type' => 'string', 'max' => 20, 'default' => 'A', 'aliases' => ['rev', 'issue']],
                'status' => ['label' => 'Status', 'type' => 'enum', 'enum' => $status, 'default' => 'ACTIVE', 'aliases' => ['state', 'condition']],
                'material' => ['label' => 'Material', 'type' => 'string', 'max' => 50, 'aliases' => ['box material', 'made of']],
                'cavities' => ['label' => 'Cavities', 'type' => 'int', 'min' => 1, 'default' => '1', 'aliases' => ['impressions', 'no of cavities', 'cavity count', 'cav']],
                'nominal_core_weight_kg' => ['label' => 'Core weight (kg)', 'type' => 'decimal', 'scale' => 4, 'min' => 0, 'aliases' => ['weight', 'core weight', 'weight kg']],
                'description' => ['label' => 'Description', 'type' => 'text', 'aliases' => ['details']],
                'notes' => ['label' => 'Notes', 'type' => 'text', 'aliases' => ['remarks', 'comment', 'comments']],
                'commissioned_on' => ['label' => 'Commissioned on', 'type' => 'date', 'aliases' => ['commissioned', 'date commissioned', 'in service', 'made on', 'date']],
            ],
        ],
        'mould_configuration' => [
            'label' => 'Mould configurations', 'model' => \App\Models\MouldConfiguration::class, 'table' => 'mould_configurations', 'kind' => 'mould_configuration', 'tool' => true,
            'blurb' => 'Ways a mould is built up: flask sets, layer counts and moulding type.',
            'match' => ['code' => 'Code', 'name' => 'Name', 'mark_code' => 'Engraving mark'],
            'fields' => [
                'code' => ['label' => 'Code', 'type' => 'string', 'max' => 50, 'aliases' => ['configuration code', 'config code', 'id']],
                'mark_code' => ['label' => 'Engraving mark', 'type' => 'mark', 'aliases' => ['mark', 'engraved mark', 'stamp']],
                'name' => ['label' => 'Name', 'type' => 'string', 'max' => 150, 'required' => true, 'aliases' => ['configuration name', 'config name', 'description', 'title']],
                'mould_type' => ['label' => 'Mould type', 'type' => 'string', 'max' => 40, 'default' => 'GREEN_SAND', 'aliases' => ['type', 'moulding type', 'sand type', 'process']],
                'nominal_layer_count' => ['label' => 'Layers', 'type' => 'int', 'min' => 1, 'max' => 100, 'default' => '2', 'aliases' => ['layer count', 'no of layers', 'layers per mould', 'tiers']],
                'is_active' => ['label' => 'Active', 'type' => 'bool', 'default' => '1', 'aliases' => ['active', 'enabled', 'status']],
                'description' => ['label' => 'Description', 'type' => 'text', 'aliases' => ['details']],
                'notes' => ['label' => 'Notes', 'type' => 'text', 'aliases' => ['remarks', 'comment', 'comments']],
            ],
        ],
    ],
];
