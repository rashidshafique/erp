<?php

namespace App\Domains\Documents;

use App\Domains\Core\ModuleServiceProvider;
use App\Domains\Documents\Models\ControlledDocumentRevision;

/** The register of controlled documents: revisions, ordered approval, the revision in force, and review dates. */
class DocumentsServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'documents';
    }

    protected function moduleLabel(): string
    {
        return 'Controlled documents';
    }

    protected function moduleDescription(): string
    {
        return 'Procedures, work instructions, forms and specifications with revisions, approval, review dates and the revision in force.';
    }

    protected function modulePath(): string
    {
        return __DIR__;
    }

    protected function navItems(): array
    {
        return [
            ['group' => 'quality', 'label' => 'Controlled documents', 'route' => 'documents.index', 'icon' => 'book', 'active' => ['documents.*'],
                'count' => fn (): int => ControlledDocumentRevision::where('status', 'IN_REVIEW')->count()],
        ];
    }

    protected function permissionCatalog(): array
    {
        return [
            'documents.manage' => 'Controlled documents: create, revise, submit, make obsolete',
            'documents.approve' => 'Controlled documents: approve or reject revisions',
        ];
    }

    protected function permissionRoutes(): array
    {
        return [
            ['pattern' => 'documents.revisions.decide', 'permission' => 'documents.approve'],
            ['pattern' => 'documents.*', 'permission' => 'documents.manage'],
        ];
    }

    protected function searchTypes(): array
    {
        return [
            'document' => ['label' => 'Document', 'table' => 'controlled_documents', 'code' => 'document_number', 'name' => 'title', 'route' => 'documents.show', 'permission' => null, 'words' => ['doc', 'docs', 'document', 'documents', 'sop']],
        ];
    }
}
