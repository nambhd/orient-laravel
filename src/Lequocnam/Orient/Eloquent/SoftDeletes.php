<?php

namespace Lequocnam\Orient\Eloquent;

use Illuminate\Database\Eloquent\SoftDeletes as BaseSoftDeletes;

trait SoftDeletes
{
	use BaseSoftDeletes;

	/**
     * Get the fully qualified "deleted at" column.
     *
     * @return string
     */
    public function getQualifiedDeletedAtColumn()
    {
        // return $this->getTable().'.'.$this->getDeletedAtColumn();
        return $this->getDeletedAtColumn();
    }
}
