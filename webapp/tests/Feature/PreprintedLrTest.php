<?php

use App\Modules\Invoicing\Design\DesignSchema;
use App\Modules\Invoicing\Models\DocumentTemplate;
use App\Platform\Tenancy\CompanyContext;

// The LR on a pre-printed form (M51): a theme that prints the values only,
// each at its slot's place in mm from the paper's corner, moved by the
// printer offset; no boxes, labels or logo; a grid on screen only.

it('keeps a pre-printed form’s positions inside the paper and drops unknown values', function () {
    $design = DesignSchema::normalize('lr', ['theme' => 'preprinted', 'offset_x' => 35, 'slots' => [
        'lr_number' => ['on' => '1', 'x' => 150, 'y' => 18, 'w' => 45], 'total' => ['on' => '0', 'x' => 500], 'stamp' => ['on' => 1],
    ]], $dropped);

    expect($design['slots']['lr_number'])->toBe(['on' => true, 'x' => 150, 'y' => 18, 'w' => 45])
        ->and($design['slots']['total'])->toMatchArray(['on' => false, 'x' => 300])->and($design['offset_x'])->toBe(20)
        ->and($design['copies'])->toHaveCount(1)->and($design['slots']['consignor']['on'])->toBeTrue()
        ->and($dropped)->toContain('Position: “stamp” is not one of the values, left out')->toContain('Printer offset, across: kept between -20 and 20');
    // An invoice design carries no positions.
    expect(DesignSchema::defaults('invoice')['slots'])->toBe([]);
});

it('prints the LR’s values where the pre-printed form has its boxes', function () {
    $w = invWorld();
    $c = $w['loads'][0];
    $this->post(route('settings.templates.store'), ['family' => 'lr', 'theme' => 'preprinted', 'name' => 'Our printed LR book'])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    $template = DocumentTemplate::where('name', 'Our printed LR book')->firstOrFail();
    expect($template->is_default)->toBeTrue();

    // The editor offers the positions; LR no. moved, the consignee left off, everything 2 mm right for the printer.
    $this->get(route('settings.templates.edit', $template))->assertOk()->assertSeeText('Positions on a pre-printed form');
    $design = $template->design;
    $design['slots']['lr_number'] = ['on' => '1', 'x' => 150, 'y' => 20, 'w' => 45];
    unset($design['slots']['consignee']['on']);
    $design['offset_x'] = 2;
    $this->put(route('settings.templates.update', $template), ['name' => 'Our printed LR book', 'design' => $design])->assertSessionHasNoErrors();

    $html = $this->get(route('trips.loads.lr', [$c->trip_id, $c]))->assertOk()->getContent();
    expect($html)->toContain('<body class="t-preprinted ')->toContain('margin: 0mm;')
        ->toContain('data-slot="lr_number"')->toContain('left: 152mm; top: 20mm; width: 45mm')
        ->toContain('data-slot="consignor"')->not->toContain('data-slot="consignee"')
        ->toContain($c->lr_number)->toContain('ACC Limited')
        // The form has its own labels and boxes: none of ours.
        ->not->toContain('Proof of delivery')->not->toContain('Consignor’s signature')->not->toContain('class="sheet" style="background');
    expect(substr_count($html, '<div class="sheet">'))->toBe(1);
});
