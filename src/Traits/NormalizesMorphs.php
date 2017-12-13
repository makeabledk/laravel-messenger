<?php

namespace Cmgmyr\Messenger\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait NormalizesMorphs
{
    /**
     * @param $modelId
     * @param $modelType
     * @return array
     */
    protected function getMorphIdAndType($modelId, $modelType)
    {
        if ($modelType === null) {
            return [$modelId->getKey(), $modelId->getMorphClass()];
        }
        return func_get_args();
    }
}
