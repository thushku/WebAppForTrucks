<?php

namespace App\Modules\Tax\Models;

use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/** A part a tax is made of: CGST, SGST, UTGST, IGST, VAT. */
class TaxType extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id', 'company_id'];
}
