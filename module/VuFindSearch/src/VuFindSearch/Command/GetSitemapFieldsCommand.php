<?php

namespace VuFindSearch\Command;

use VuFindSearch\Backend\BackendInterface;
use VuFindSearch\Command\CallMethodCommand;
use VuFindSearch\Command\CommandInterface;
use VuFindSearch\Command\Feature\QueryOffsetLimitTrait;
use VuFindSearch\Feature\GetSitemapFieldsInterface;
use VuFindSearch\ParamBag;
use VuFindSearch\Query\QueryInterface;

/**
 * Similar to getIds Command, but with sitemap-specific result fields
 */
class GetSitemapFieldsCommand extends CallMethodCommand
{
    use QueryOffsetLimitTrait;

    public function __construct(
        string $backendId,
        QueryInterface $query,
        int $offset = 0,
        int $limit = 20,
        ?ParamBag $params = null
    ) {
        $this->query = $query;
        $this->offset = $offset;
        $this->limit = $limit;
        parent::__construct(
            $backendId,
            GetSitemapFieldsInterface::class,
            'getSitemapFields',
            $params,
            'getsitemapfields'
        );
    }

    public function getArguments(): array
    {
        return [
            $this->getQuery(),
            $this->getOffset(),
            $this->getLimit(),
            $this->getSearchParameters(),
        ];
    }

    public function execute(BackendInterface $backend): CommandInterface
    {
        if (!($backend instanceof GetSitemapFieldsInterface)) {
            $this->interface = BackendInterface::class;
            $this->method = 'search';
        }
        return parent::execute($backend);
    }
}
