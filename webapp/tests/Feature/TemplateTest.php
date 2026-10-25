<?php

use App\Modules\Invoicing\Design\DesignSchema;
use App\Modules\Invoicing\Models\DocumentTemplate;
use App\Modules\Invoicing\Models\DocumentTemplateVersion;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Invoicing\Models\Receipt;
use App\Modules\Settings\Models\Branch;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Http\UploadedFile;

// Templates and the template editor (M10): the design schema, which design a
// document prints with, versions and rollback, the live preview, import and
// export, the LR copy set and the receipt themes.

/** The editor's form for a design, as the page posts it. */
function tplForm(array $design, string $name = 'Our bill'): array
{
    return ['name' => $name, 'design' => $design];
}

/** A template made through the screens; the context is set again after the request. */
function tplCreate(array $w, string $family, string $theme, string $name): DocumentTemplate
{
    test()->post(route('settings.templates.store'), ['family' => $family, 'theme' => $theme, 'name' => $name])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);

    return DocumentTemplate::where('name', $name)->firstOrFail();
}

it('keeps only what a design may hold, and says in plain words what it left out', function () {
    $design = DesignSchema::normalize('invoice', [
        'theme' => 'modern', 'accent' => 'red; background:url(x)', 'font' => 'comic', 'base_pt' => 30, 'margin_mm' => '9',
        'fields' => ['bank' => false, 'qr' => 'maybe', 'secret' => true],
        'titles' => ['tax_invoice' => '<b>Freight bill</b>', 'nonsense' => 'x'],
        'labels' => ['bill_to' => "Billed\u{202E} to"],
        'copies' => array_fill(0, 8, ['label' => 'Copy', 'tint' => '#ffeeee']),
        'script' => '<script>alert(1)</script>',
    ], $dropped);

    expect($design)->toMatchArray(['theme' => 'modern', 'accent' => '#1F6FEB', 'font' => 'noto', 'base_pt' => 12.0, 'margin_mm' => 9])
        ->and($design['fields']['bank'])->toBeFalse()
        ->and($design['fields']['qr'])->toBeTrue()
        ->and($design['fields'])->not->toHaveKey('secret')
        ->and($design['titles']['tax_invoice'])->toBe('Freight bill')
        ->and($design['labels']['bill_to'])->toBe('Billed  to')
        ->and($design['copies'])->toHaveCount(6)
        ->and($design['copies'][0]['tint'])->toBe('#FFEEEE')
        ->and($design)->not->toHaveKey('script');

    $text = implode(' | ', $dropped);
    expect($text)->toContain('Accent colour')->toContain('Font: not one of the choices')->toContain('Text size: kept between 8 and 12')
        ->toContain('“secret” is not one of the switches')->not->toContain('<script>')
        ->toContain('“nonsense” is not one of the titles')->toContain('markup and invisible characters taken out')
        ->toContain('At most 6 copies')->toContain('“script” is not part of a design');

    // The stylesheet reads checked values only, even from a design that skipped normalize().
    expect(DesignSchema::cssVariables(['accent' => 'red;}', 'font' => 'x', 'base_pt' => 10, 'margin_mm' => 12, 'logo_mm' => 18, 'paper' => 'A4', 'density' => 'compact']))
        ->toContain('--accent: #111827')->not->toContain('red;}')
        ->and(DesignSchema::contrastOnWhite('#FFFF00'))->toBeLessThan(3.0)
        ->and(DesignSchema::contrastOnWhite('#111827'))->toBeGreaterThan(10.0);
});

it('prints with the built-in design until a template is made, then with the default; every save is a version', function () {
    $w = invWorld();
    $invoice = invDraft($w);
    $this->get(route('invoices.print', $invoice))->assertOk()->assertSeeText('Tax invoice')->assertSee('<body class="t-gst_classic ', false)->assertSeeText('Bill to');

    $template = tplCreate($w, 'invoice', 'modern', 'Blue bill');
    expect($template->is_default)->toBeTrue()->and($template->version)->toBe(1)->and($template->versions()->count())->toBe(1);

    $design = $template->designNormalized();
    $design['titles']['tax_invoice'] = 'Freight bill';
    $design['labels']['bill_to'] = 'Billed to';
    $design['accent'] = '#7C3AED';
    $design['fields'] = array_fill_keys(array_keys(DesignSchema::FIELDS['invoice']), 1);
    unset($design['fields']['computer_generated']);          // a switch turned off posts nothing
    $design['footer'] = 'Subject to Kochi jurisdiction';
    $design['custom'] = [['label' => 'Udyam', 'value' => 'UDYAM-KL-07-0012345'], ['label' => '', 'value' => '']];
    $this->put(route('settings.templates.update', $template), tplForm($design, 'Blue bill'))->assertSessionHasNoErrors()->assertSessionHas('status', 'Saved as version 2.');

    app(CompanyContext::class)->set($w['company']);
    $template->refresh();
    expect($template->version)->toBe(2)->and($template->design['fields']['computer_generated'])->toBeFalse()
        ->and($template->design['custom'])->toBe([['label' => 'Udyam', 'value' => 'UDYAM-KL-07-0012345']])
        ->and(DocumentTemplateVersion::where('document_template_id', $template->id)->pluck('version')->sort()->values()->all())->toBe([1, 2]);

    // Saving the same design again adds no version.
    $this->put(route('settings.templates.update', $template), tplForm($template->design, 'Blue bill'));
    app(CompanyContext::class)->set($w['company']);
    expect($template->fresh()->version)->toBe(2);

    $this->get(route('invoices.print', $invoice))->assertOk()->assertSee('<body class="t-modern ', false)->assertSee('--accent: #7C3AED', false)
        ->assertSeeText('Freight bill')->assertSeeText('Billed to')->assertSeeText('Subject to Kochi jurisdiction')->assertSeeText('UDYAM-KL-07-0012345')
        ->assertDontSeeText('This is a computer-generated document.');
});

it('freezes the design on a bill at issue: a later change of the template does not reprint it', function () {
    $w = invWorld();
    $template = tplCreate($w, 'invoice', 'gst_classic', 'Classic');
    $design = $template->designNormalized();
    $design['titles']['tax_invoice'] = 'Freight bill';
    $this->put(route('settings.templates.update', $template), tplForm($design, 'Classic'));

    $invoice = invDraft($w);
    $this->post(route('invoices.finalise', $invoice))->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    expect($invoice->fresh()->design_snapshot)->toMatchArray(['theme' => 'gst_classic', 'template' => ['id' => $template->id, 'name' => 'Classic', 'version' => 2]]);

    $design['titles']['tax_invoice'] = 'Something else';
    $design['theme'] = 'minimal';
    $this->put(route('settings.templates.update', $template->fresh()), tplForm($design, 'Classic'));

    $this->get(route('invoices.print', $invoice))->assertSeeText('Freight bill')->assertDontSeeText('Something else')->assertSee('<body class="t-gst_classic ', false);
});

it('picks the bill’s own choice, then the client’s template, then the branch’s, then the default', function () {
    $w = invWorld();
    $default = tplCreate($w, 'invoice', 'gst_classic', 'Default bill');
    $client  = tplCreate($w, 'invoice', 'modern', 'ACC bill');
    $branchT = tplCreate($w, 'invoice', 'minimal', 'Branch bill');
    $branch  = app(CompanyContext::class)->runAs($w['company'], fn () => Branch::create(['name' => 'Mumbai branch', 'code' => 'BOM', 'state_code' => '27']));
    expect($default->fresh()->is_default)->toBeTrue()->and($client->fresh()->is_default)->toBeFalse();

    $invoice = invDraft($w);
    $this->get(route('invoices.print', $invoice))->assertSee('<body class="t-gst_classic ', false);

    $this->put(route('settings.templates.assign', $branchT), ['branch_ids' => [$branch->id]])->assertSessionHasNoErrors();
    app(CompanyContext::class)->runAs($w['company'], fn () => Invoice::whereKey($invoice->id)->update(['branch_id' => $branch->id]));
    $this->get(route('invoices.print', $invoice))->assertSee('<body class="t-minimal ', false);

    $this->put(route('settings.templates.assign', $client), ['party_ids' => [$w['client']->id]])->assertSessionHasNoErrors();
    $this->get(route('invoices.print', $invoice))->assertSee('<body class="t-modern ', false);

    // Giving the client to another template takes it off this one.
    $this->put(route('settings.templates.assign', $default), ['party_ids' => [$w['client']->id]]);
    app(CompanyContext::class)->set($w['company']);
    expect($client->fresh()->party_ids)->toBeNull()->and($default->fresh()->party_ids)->toBe([$w['client']->id]);

    // The builder's choice beats them all.
    $this->get(route('invoices.edit', $invoice))->assertOk()->assertSee('name="document_template_id"', false);
    app(CompanyContext::class)->set($w['company']);
    $lines = $invoice->lines()->get()->map(fn ($l) => ['id' => $l->id, 'service_class' => $l->service_class, 'description' => $l->description, 'quantity' => 1, 'unit_rate' => (float) $l->unit_rate])->all();
    $this->put(route('invoices.update', $invoice), ['issue_date' => '2026-10-25', 'due_date' => '2026-11-24', 'discount_type' => 'none', 'document_template_id' => $client->id, 'lines' => $lines])->assertSessionHasNoErrors();
    $this->get(route('invoices.print', $invoice))->assertSee('<body class="t-modern ', false);

    // Another company's template cannot be chosen.
    $other = makeCompany();
    $foreign = app(CompanyContext::class)->runAs($other, fn () => DocumentTemplate::create(['family' => 'invoice', 'name' => 'Theirs', 'design' => DesignSchema::defaults('invoice')]));
    $this->put(route('invoices.update', $invoice), ['issue_date' => '2026-10-25', 'due_date' => '2026-11-24', 'discount_type' => 'none', 'document_template_id' => $foreign->id, 'lines' => $lines])
        ->assertSessionHasErrors('document_template_id');
    $this->get(route('settings.templates.edit', $foreign->id))->assertNotFound();
});

it('brings an old version back as the newest, and keeps the default from being removed', function () {
    $w = invWorld();
    $template = tplCreate($w, 'invoice', 'gst_classic', 'Classic');
    $design = $template->designNormalized();
    $design['accent'] = '#B91C1C';
    $this->put(route('settings.templates.update', $template), tplForm($design, 'Classic'));

    $this->post(route('settings.templates.rollback', $template), ['version' => 1])->assertSessionHasNoErrors()->assertSessionHas('status', 'Version 1 brought back as version 3.');
    app(CompanyContext::class)->set($w['company']);
    expect($template->fresh()->version)->toBe(3)->and($template->fresh()->design['accent'])->toBe('#111827')
        ->and($template->versions()->count())->toBe(3);

    $this->delete(route('settings.templates.destroy', $template))->assertSessionHasErrors('template');
    $copy = (function () use ($w, $template) {
        $this->post(route('settings.templates.duplicate', $template))->assertSessionHasNoErrors();
        app(CompanyContext::class)->set($w['company']);

        return DocumentTemplate::where('name', 'Classic (copy)')->firstOrFail();
    })->call($this);
    $this->delete(route('settings.templates.destroy', $copy))->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    expect(DocumentTemplate::count())->toBe(1);
});

it('draws the live preview from the form without saving, on the latest bill or a sample', function () {
    $w = invWorld();
    $template = tplCreate($w, 'invoice', 'gst_classic', 'Classic');
    $design = $template->designNormalized();
    $design['accent'] = '#0F766E';
    $design['titles']['tax_invoice'] = 'Preview title';

    // No bill issued yet: a sample with our own name.
    $this->post(route('settings.templates.preview', 'invoice'), ['design' => $design])->assertOk()
        ->assertSee('--accent: #0F766E', false)->assertSeeText('Preview title')->assertSeeText('Sample Cements Pvt Ltd')->assertSeeText($w['company']->name);
    app(CompanyContext::class)->set($w['company']);
    expect($template->fresh()->version)->toBe(1);

    $this->get(route('settings.templates.edit', $template))->assertOk()->assertSee('srcdoc=', false)->assertSeeText('A sample with your name and bank');

    // With an issued bill, the preview is that bill.
    $invoice = invDraft($w);
    $this->post(route('invoices.finalise', $invoice));
    $this->post(route('settings.templates.preview', 'invoice'), ['design' => $design])->assertSeeText('INV/26-27/00001')->assertSeeText('ACC Limited');

    // The editor is for those allowed to change templates.
    signInTo($w['company'], 'viewer');
    $this->post(route('settings.templates.preview', 'invoice'), ['design' => $design])->assertForbidden();
    $this->get(route('settings.templates'))->assertForbidden();
});

it('exports a design and imports it again, leaving out what does not fit', function () {
    $w = invWorld();
    $template = tplCreate($w, 'lr', 'compact_a5', 'Small LR');
    $json = $this->get(route('settings.templates.export', $template))->assertOk()->assertHeader('Content-Type', 'application/json')->getContent();
    $data = json_decode($json, true);
    expect($data)->toMatchArray(['name' => 'Small LR', 'family' => 'lr', 'theme' => 'compact_a5', 'paper' => 'A5']);

    $data['accent'] = 'javascript:alert(1)';
    $data['fields']['unknown_box'] = true;
    $file = UploadedFile::fake()->createWithContent('small.design.json', json_encode($data));
    $this->post(route('settings.templates.import'), ['family' => 'lr', 'file' => $file])->assertSessionHasNoErrors()->assertSessionHas('dropped');
    app(CompanyContext::class)->set($w['company']);

    $imported = DocumentTemplate::where('name', 'Small LR 2')->firstOrFail();
    expect($imported->design['theme'])->toBe('compact_a5')->and($imported->design['accent'])->toBe('#111827')->and($imported->is_default)->toBeFalse();
    $this->get(route('settings.templates.edit', $imported))->assertOk()->assertSeeText('Saved, but some values were not kept');

    $bad = UploadedFile::fake()->createWithContent('bad.json', 'not json');
    $this->post(route('settings.templates.import'), ['family' => 'lr', 'file' => $bad])->assertSessionHasErrors('file');
});

it('prints the LR copy set of its template: labels, tints and the freight hidden where chosen', function () {
    $w = invWorld();
    $template = tplCreate($w, 'lr', 'compact_a5', 'Two copies');
    $design = $template->designNormalized();
    $design['copies'] = [
        ['label' => 'Party copy', 'tint' => '#ffffff'],
        ['label' => 'Truck copy', 'tint' => '#fffbeb', 'hide_freight' => '1'],
        ['label' => 'Gone', 'tint' => '#ffffff', 'remove' => '1'],
    ];
    $design['fields'] = array_fill_keys(array_diff(array_keys(DesignSchema::FIELDS['lr']), ['pod_box']), 1);
    $design['labels']['consignor'] = 'Sender';
    $this->put(route('settings.templates.update', $template), tplForm($design, 'Two copies'))->assertSessionHasNoErrors();

    $c = $w['loads'][0];
    $html = $this->get(route('trips.loads.lr', [$c->trip_id, $c]))->assertOk()->getContent();
    expect(substr_count($html, 'class="sheet"'))->toBe(2)
        ->and($html)->toContain('Party copy')->toContain('Truck copy')->not->toContain('Gone')->toContain('size: A5')
        ->toContain('background: #FFFBEB')->toContain('Sender')->not->toContain('Proof of delivery')
        ->and(substr_count($html, '₹62,900.00'))->toBe(1);
});

it('prints a receipt on the thermal 80 mm theme', function () {
    $w = invWorld();
    $invoice = invDraft($w);
    $this->post(route('invoices.finalise', $invoice));
    $this->post(route('receipts.store'), ['party_id' => $w['client']->id, 'receipt_date' => '2026-10-25', 'amount' => 75520, 'mode' => 'upi', 'reference' => 'UPI-881', 'narration' => 'October bill', 'allocation' => 'oldest'])->assertSessionHasNoErrors();
    tplCreate($w, 'receipt', 'thermal_80', 'Roll');
    app(CompanyContext::class)->set($w['company']);

    $this->get(route('receipts.print', Receipt::first()))->assertOk()->assertSee('<body class="t-thermal_80 ', false)->assertSee('size: 80mm 297mm', false)
        ->assertSee('--sheet: 80mm', false)->assertSeeText('Money receipt')->assertSeeText('INV/26-27/00001');
    $this->get(route('settings.templates', ['family' => 'receipt']))->assertOk()->assertSeeText('Roll')->assertSeeText('Thermal 80 mm');
});
