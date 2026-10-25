<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Platform\Settings\PlatformSettings;
use App\Platform\Website\Pricing;
use App\Platform\Website\SiteText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * The public website (blueprint 04 §5, the website guideline): home,
 * features, pricing from the super-admin's plans with a coupon box, a
 * searchable FAQ, about us and the legal pages. Pages hold no personal data,
 * so they read the same for everyone.
 */
class SiteController extends Controller
{
    public function __construct(private PlatformSettings $settings) {}

    public function home(Request $request): View|RedirectResponse
    {
        // Signed in: straight to work. The site stays one click away in the footer of the sign-in page.
        if ($request->user() && ! $request->boolean('site')) {
            return redirect()->route('dashboard');
        }

        return view('site.home', ['trialDays' => $this->settings->trialDays(), 'features' => array_map(fn ($s) => [$s['icon'], $s['title'], $s['short']], $this->sections())]);
    }

    public function features(): View
    {
        return view('site.features', ['trialDays' => $this->settings->trialDays(), 'sections' => $this->sections()]);
    }

    public function pricing(Request $request, Pricing $pricing): View
    {
        $data = $request->validate(['country' => ['nullable', 'in:IN,AE'], 'coupon' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9_-]*$/']]);
        $country = $data['country'] ?? 'IN';

        return view('site.pricing', [
            'country' => $country, 'trialDays' => $this->settings->trialDays(),
            'table' => $pricing->table($country, $data['coupon'] ?? null),
            'questions' => array_slice($this->faqGroups()[__('Trial and payment')], 0, 4),
        ]);
    }

    public function faq(): View
    {
        return view('site.faq', ['groups' => $this->faqGroups()]);
    }

    public function about(SiteText $text): View
    {
        return view('site.text', ['page' => 'about', 'title' => __('About us'), 'html' => $text->html('about'), 'updated' => null]);
    }

    public function legal(string $page, SiteText $text): View
    {
        abort_unless(in_array($page, ['terms', 'privacy', 'refund'], true), 404);

        return view('site.text', ['page' => $page, 'title' => __(SiteText::PAGES[$page]), 'html' => $text->html($page), 'updated' => $text->updatedOn($page) ?? '2026-10-26']);
    }

    /** For search engines: the public pages. */
    public function sitemap(): Response
    {
        $urls = collect(['home', 'site.features', 'site.pricing', 'site.faq', 'site.about', 'site.contact', 'signup'])->map(fn ($r) => route($r))
            ->merge(collect(['terms', 'privacy', 'refund'])->map(fn ($p) => route('site.legal', $p)));
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            . $urls->map(fn ($u) => '<url><loc>' . e($u) . '</loc></url>')->join('') . '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    /** The feature sections: the features page in full, the home page in short. */
    private function sections(): array
    {
        return [
            ['id' => 'trips', 'icon' => 'route', 'title' => __('Trips'), 'short' => __('Full and part loads, stops, your trucks or hired ones, papers checked before dispatch.'),
                'lead' => __('Plan a trip in a minute and send it to the crew’s phones. Full load, part load or both; several consignments with their own pickup and drop; your truck or one you hire.'),
                'points' => [__('Loads, stops and LR details per consignment'), __('Hired trucks with a hire challan, advance and balance'), __('Broker commission on the trip'), __('Expired RC, permit, fitness or licence blocks dispatch'), __('Driver change on the way, kept in the log')],
                'sample' => [[__('Trip'), 'T-26-0141', ''], [__('Kochi → Bhiwandi → Mumbai'), __('2 loads'), ''], [__('Papers'), __('All valid'), 'good'], [__('Hired truck balance'), '₹42,000', 'warn']]],
            ['id' => 'crew-app', 'icon' => 'phone', 'title' => __('Crew app'), 'short' => __('Fuel, tolls, loading and delivery with GPS, time and photos — works offline.'),
                'lead' => __('Drivers and helpers sign in with a code from the office — no email, no password. Big buttons, their language, and it keeps working without network.'),
                'points' => [__('Fuel, toll, police, RTO and every road payment'), __('Each entry carries GPS, the phone’s time and up to three photos'), __('Loading and delivery with an e-POD signature'), __('My Khata: what the driver holds and is owed'), __('One app for a driver who works for several companies')],
                'sample' => [[__('Diesel, HP Aluva'), '₹9,500', ''], [__('GPS and photo'), __('Saved'), 'good'], [__('No network'), __('Sends later'), 'warn'], [__('Trip cash with Ravi'), '₹3,750', '']]],
            ['id' => 'fuel-control', 'icon' => 'fuel', 'title' => __('Fuel control'), 'short' => __('Litres, rate and odometer checked against the truck’s mileage.'),
                'lead' => __('Every fill shows litres, rate, pump and odometer; the app compares the mileage with the truck’s standard and marks what does not add up.'),
                'points' => [__('Km per litre per truck, against its standard'), __('A photo reused from an old bill is caught'), __('Crew cash, fuel card, FASTag and pump credit kept apart'), __('Payments without receipt counted by person and route')],
                'sample' => [[__('KL-07-AB-1234'), '3.6 km/l', 'good'], [__('TN-38-CQ-4410'), '2.9 km/l', 'bad'], [__('Standard'), '3.5 km/l', ''], [__('Reused photo'), __('Flagged'), 'bad']]],
            ['id' => 'invoices-and-lr', 'icon' => 'receipt', 'title' => __('Invoices and LR'), 'short' => __('Lorry receipts with copies, bills with GST or VAT, receipts with TDS.'),
                'lead' => __('The LR is made from the trip, the bill from the LRs, the receipt from the bill. Print, PDF or WhatsApp, in your own design.'),
                'points' => [__('LR with consignor, consignee and driver copies'), __('Single or consolidated bills; credit and debit notes'), __('Receipts with TDS and deductions'), __('Your logo, colours and fields in the template editor'), __('What is ready to bill and what is overdue')],
                'sample' => [[__('Ready to bill'), '6', ''], [__('Billed, not sent'), '2', 'warn'], [__('Overdue'), '₹1,28,000', 'bad'], [__('Received this month'), '₹6,40,500', 'good']]],
            ['id' => 'accounts-and-tax', 'icon' => 'ledger', 'title' => __('Accounts and tax'), 'short' => __('Ledgers, P&L per trip and truck, GST and TDS data, Tally export.'),
                'lead' => __('Every trip, bill, payment and road entry posts to double-entry books once, with the rules of your country.'),
                'points' => [__('Ledgers for drivers, clients, suppliers and vendors'), __('Profit per trip, per truck and per month'), __('GST for transport (forward charge or RCM), TDS 194C; UAE VAT'), __('Trial balance, P&L, balance sheet, ageing'), __('GSTR-1 data and a Tally export')],
                'sample' => [[__('Trip profit'), '₹38,200', 'good'], [__('Truck profit, October'), '₹1,12,400', 'good'], [__('TDS receivable'), '₹5,320', ''], [__('Books'), __('Balanced'), 'good']]],
            ['id' => 'trucks-and-documents', 'icon' => 'truck', 'title' => __('Trucks and documents'), 'short' => __('RC, insurance, permits and licences with expiry reminders; service and complaints.'),
                'lead' => __('Every truck and person with its papers, their expiry dates and reminders, service schedules and complaints from the crew.'),
                'points' => [__('Document Manager with versions and expiry reminders'), __('Share a pack of papers by a link that expires'), __('Service schedules by km or days; workshop jobs'), __('Complaints raised from the crew app')],
                'sample' => [[__('Insurance, KL-07-AB-1234'), __('12 days left'), 'warn'], [__('Permit, TN-38-CQ-4410'), __('Expired'), 'bad'], [__('Service due'), __('in 1,200 km'), ''], [__('Open complaints'), '3', '']]],
            ['id' => 'live-map-and-reports', 'icon' => 'map', 'title' => __('Live map and reports'), 'short' => __('Where every truck is, the route it took, and reports for each driver and truck.'),
                'lead' => __('See every running trip on a map, replay the route with its entries, and get the reports an owner asks for.'),
                'points' => [__('Live positions, a quiet phone marked'), __('Route replay with fuel and toll entries on it'), __('Driving time without a break, alerted'), __('Reports: trip profit, road spending, fuel, drivers')],
                'sample' => [[__('Running trips'), '12', ''], [__('Phone quiet'), __('40 min'), 'warn'], [__('Driven without a break'), '5 h 10 min', 'bad'], [__('Km this month'), '48,210', '']]],
            ['id' => 'payroll', 'icon' => 'payroll', 'title' => __('Driver pay'), 'soon' => true, 'short' => __('Bata, salary and advances in one pay run (coming soon).'),
                'lead' => __('Bata per trip or per day, salary, advances and deductions in one pay run, from the same ledgers.'),
                'points' => [__('Bata already flows from trips into each driver’s ledger'), __('Pay run with salary, advances and deductions — coming soon')],
                'sample' => [[__('Bata owed to Ravi'), '₹4,200', ''], [__('Advance to recover'), '₹2,000', 'warn']]],
        ];
    }

    private function faqGroups(): array
    {
        $days = $this->settings->trialDays();

        return [
            __('The crew app') => [
                [__('Do drivers need a smartphone and an email?'), __('An Android phone is enough; an iPhone app follows. No email is needed: the office gives each driver a one-time code to sign in, and the driver sets a PIN.')],
                [__('What if there is no network on the road?'), __('The app works offline: every entry is saved on the phone first, with the time and place, and sent when the network returns. Nothing is lost and nothing is sent twice.')],
                [__('Can a driver change an entry later?'), __('Only until the office checks it. After that it is locked; corrections are new entries, and every change is in the audit log.')],
                [__('Is the driver’s location tracked all the time?'), __('Only while the driver is on duty or on a trip. The office sees the last position of each running trip and the route afterwards.')],
                [__('Which languages does the app speak?'), __('English and Hindi today; more Indian languages and Arabic follow.')],
            ],
            __('Money and books') => [
                [__('Does it handle GST for transport?'), __('Yes — forward charge or reverse charge (RCM) for a goods transport agency, place of supply by state, and TDS under section 194C on freight and hire.')],
                [__('Can my accountant keep using Tally?'), __('Yes. The books are kept here, and the accountant can download a Tally export, GSTR-1 data and every ledger as a spreadsheet.')],
                [__('Do I still need a separate accounting program?'), __('Many fleets do not: trial balance, profit and loss, the balance sheet, ageing and the GST and TDS registers are built in.')],
                [__('Does it work in the UAE?'), __('The UAE VAT rules, TRN and emirates are built in; full UAE launch with Salik and e-invoicing follows.')],
            ],
            __('Trial and payment') => [
                [__('How long is the free trial?'), __(':days days with every feature. No card is asked for.', ['days' => $days])],
                [__('What happens when the trial ends?'), __('Nothing is deleted. The company becomes read-only — you can still see and download everything — until you choose a plan.')],
                [__('How do I pay?'), __('By bank transfer or UPI to us; your plan starts as soon as the payment is recorded. Online card payment is coming.')],
                [__('Can I use a coupon?'), __('Yes. Enter it on the pricing page to see the price, then sign up from there — we apply it to your first payment.')],
                [__('Is there a discount for students?'), __('Yes, a student plan for logistics students and teachers. Ask us from the contact page.')],
            ],
            __('Data and security') => [
                [__('Who can see our data?'), __('Only the people you invite, each with the permissions you give. Bank details and ID numbers are encrypted, and every change to money, trips and people is logged.')],
                [__('Can we take our data out?'), __('Yes, at any time: Settings → Your data downloads everything as one ZIP — a spreadsheet for each kind of record and every uploaded file. Ledgers and reports also download on their own pages.')],
                [__('What happens if we leave?'), __('The owner deletes the company from Settings → Your data. It is removed 72 hours later (you can cancel until then); one encrypted copy is kept only as long as tax law requires, then removed too.')],
                [__('Are finalised bills ever changed?'), __('Never. A mistake is corrected with a credit note or by voiding the bill and issuing a new one, as the tax rules expect.')],
            ],
        ];
    }
}
