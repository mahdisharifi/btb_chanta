<?php

use App\Jobs\ApplySupplierCurrencyPriceChunk;
use App\Jobs\InvalidateSupplierBranchPublicCaches;
use App\Services\SupplierJobStatusService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config([
    'database.default' => 'job_status_check',
    'database.connections.job_status_check' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
    'queue.connections' => [
        'database' => ['driver' => 'database', 'table' => 'jobs'],
        'duplicate' => ['driver' => 'database', 'table' => 'jobs'],
        'sync' => ['driver' => 'sync'],
    ],
    'queue.failed' => ['driver' => 'database-uuids', 'database' => 'job_status_check', 'table' => 'failed_jobs'],
    'cache.default' => 'array',
]);
Schema::create('jobs', function (Blueprint $table): void {
    $table->id(); $table->text('payload'); $table->unsignedInteger('reserved_at')->nullable();
    $table->unsignedInteger('available_at');
});
Schema::create('failed_jobs', function (Blueprint $table): void {
    $table->id(); $table->text('payload');
});
function jobStatusAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function jobStatusPayload(object $job): string
{
    return json_encode(['displayName' => get_class($job), 'data' => ['command' => serialize($job)]], JSON_THROW_ON_ERROR);
}
$service = new SupplierJobStatusService;
$currency = jobStatusPayload(new ApplySupplierCurrencyPriceChunk(34, 'USD', [1, 2, 3], 6));
$other = jobStatusPayload(new ApplySupplierCurrencyPriceChunk(340, 'USD', [4], 6));
DB::table('jobs')->insert([
    ['payload' => $currency, 'reserved_at' => null, 'available_at' => time() - 1],
    ['payload' => $currency, 'reserved_at' => time(), 'available_at' => time() - 1],
    ['payload' => $currency, 'reserved_at' => null, 'available_at' => time() + 600],
    ['payload' => $other, 'reserved_at' => null, 'available_at' => time()],
    ['payload' => '{"supplierId":34}', 'reserved_at' => null, 'available_at' => time()],
]);
DB::table('failed_jobs')->insert(['payload' => $currency]);
$status = $service->summary(34);
jobStatusAssert($status['remaining'] === 3 && $status['failed'] === 1, 'Exact supplier ownership / duplicate connection count');
$group = $status['groups'][0];
jobStatusAssert($group['waiting'] === 1 && $group['processing'] === 1 && $group['delayed'] === 1, 'Separate lifecycle states');
jobStatusAssert($service->summary(340)['remaining'] === 1, 'Supplier IDs must not match by substring');
jobStatusAssert($service->summary(35)['remaining'] === 0, 'Other suppliers must see zero');

// Use real class serialization without coupling this check to constructor arguments.
$multi = (new ReflectionClass(InvalidateSupplierBranchPublicCaches::class))->newInstanceWithoutConstructor();
(new ReflectionProperty($multi, 'supplierIds'))->setValue($multi, [34, 35]);
$info = $service->describe(jobStatusPayload($multi));
jobStatusAssert($info['supplier_ids'] === [34, 35], 'Multi-supplier jobs retain all owners');
DB::table('jobs')->insert(['payload' => jobStatusPayload($multi), 'reserved_at' => null, 'available_at' => time()]);
jobStatusAssert(count($service->summary(34)['groups']) === 2, 'Types must be separate');
jobStatusAssert($service->summary(35)['remaining'] === 1, 'Shared cache task visible to each affected supplier');

Schema::create('suppliers', function (Blueprint $table): void {
    $table->id(); $table->unsignedBigInteger('user_id');
    $table->unsignedBigInteger('parent_supplier_id')->nullable();
    $table->boolean('is_active')->default(true); $table->softDeletes();
});
DB::table('suppliers')->insert([
    ['id' => 34, 'user_id' => 1, 'parent_supplier_id' => null, 'is_active' => true],
    ['id' => 35, 'user_id' => 1, 'parent_supplier_id' => 34, 'is_active' => true],
    ['id' => 340, 'user_id' => 2, 'parent_supplier_id' => null, 'is_active' => true],
]);
Illuminate\Support\Facades\Auth::setUser((new App\Models\User)->forceFill(['id' => 1]));
app('session')->driver()->put('supplier.active_branch_id', 35);
$component = new App\Livewire\Supplier\JobStatus;
$viewData = $component->render()->getData();
jobStatusAssert($viewData['supplier']->id === 35 && $viewData['status']['remaining'] === 1, 'Component resolves selected owned branch on server');
app('session')->driver()->put('supplier.active_branch_id', 340);
$viewData = $component->render()->getData();
jobStatusAssert($viewData['supplier']->id === 34 && $viewData['status']['remaining'] === 4, 'Foreign branch selection cannot expose another supplier');
$html = $component->render()->render();
jobStatusAssert(!str_contains($html, 'جاب') && str_contains($html, 'موضوع عملیات:'), 'User-facing copy describes operations and shows their subjects');
jobStatusAssert(str_contains($html, 'نرخ ارز و تنظیمات ذخیره‌شدهٔ شما'), 'Currency operations explain their relation to saved user settings');

class JobStatusUnsafePayload
{
    public int $supplierId = 34;
    public function __wakeup(): void { throw new RuntimeException('Must never execute'); }
}
$info = $service->describe(jobStatusPayload(new JobStatusUnsafePayload));
jobStatusAssert($info['type'] === 'JobStatusUnsafePayload', 'Unknown job classified without executing its class');
jobStatusAssert($info['label'] === 'سایر عملیات شعبه', 'Unknown operation does not expose a technical class name');
jobStatusAssert($service->describe('{broken') === null, 'Malformed JSON ignored');
jobStatusAssert($service->describe('{"data":{"command":"broken"}}') === null, 'Malformed serialized data ignored');
jobStatusAssert($service->describe(jobStatusPayload((object) ['productSupplierId' => 34])) === null, 'Product IDs never imply supplier ownership');
DB::table('jobs')->delete();
jobStatusAssert($service->summary(34)['remaining'] === 0 && $service->summary(34)['failed'] === 1, 'Completed jobs disappear; failed records remain separate');
jobStatusAssert(str_contains($component->render()->render(), '<section'), 'Failed-only queue remains visible for follow-up');
DB::table('failed_jobs')->delete();
$emptyHtml = $component->render()->render();
jobStatusAssert(!str_contains($emptyHtml, '<section') && !str_contains($emptyHtml, '<details'), 'Empty queue has no visible status card or details');
jobStatusAssert(str_contains($emptyHtml, 'wire:poll.10s'), 'Polling survives an empty queue so new jobs can appear');
DB::table('jobs')->insert(['payload' => $currency, 'reserved_at' => null, 'available_at' => time()]);
jobStatusAssert(str_contains($component->render()->render(), '<section'), 'A new job makes the previously empty card reappear');
DB::table('jobs')->delete();
jobStatusAssert(!str_contains($component->render()->render(), '<section'), 'The card disappears again when the last job finishes');

// Reproduce the currency request: two direct jobs plus four broadcast jobs.
function jobStatusPriceEvent(array $supplierIds, array $updates = []): App\Events\ProductPricesUpdated
{
    return new App\Events\ProductPricesUpdated(
        productId: 1282, priceRevision: 6, audienceRole: 'retailer',
        supplierUpdates: $updates, productPrices: [], productTiersCount: 0,
        productLowest: null, productHighest: null, productAverage: null,
        updatedAt: '2026-11-12T18:00:00+03:30', supplierIds: $supplierIds,
    );
}
$event = jobStatusPriceEvent([34]);
$wrapped = jobStatusPayload(new Illuminate\Broadcasting\BroadcastEvent($event));
jobStatusAssert($service->describe($wrapped)['supplier_ids'] === [34], 'Broadcast wrapper attribution is read from the event');
jobStatusAssert(!array_key_exists('supplierIds', $event->broadcastWith()) && !array_key_exists('supplier_ids', $event->broadcastWith()), 'Queue-only ownership must not leak into public broadcast payload');
$legacy = jobStatusPayload(new Illuminate\Broadcasting\BroadcastEvent(jobStatusPriceEvent([], [['supplier_id' => 34]])));
jobStatusAssert($service->describe($legacy)['supplier_ids'] === [34], 'Legacy visible-audience events can use explicit supplier updates');
$unattributed = jobStatusPayload(new Illuminate\Broadcasting\BroadcastEvent(jobStatusPriceEvent([])));
jobStatusAssert($service->describe($unattributed) === null, 'Legacy event without ownership must not be assigned by product ID');
$initial = [$currency, jobStatusPayload(new App\Jobs\RebaseSupplierCurrencyPrices(34, 'USD', 6)), $wrapped, $wrapped, $wrapped, $wrapped];
foreach ($initial as $payload) {
    DB::table('jobs')->insert(['payload' => $payload, 'reserved_at' => null, 'available_at' => time()]);
}
jobStatusAssert($service->summary(34)['remaining'] === 6, 'All six owned operations are counted, including nested broadcast jobs');
jobStatusAssert($service->summary(340)['remaining'] === 0, 'Wrapped events do not leak to another supplier');
for ($index = 0; $index < 25; $index++) {
    DB::table('jobs')->insert(['payload' => $currency, 'reserved_at' => null, 'available_at' => time()]);
}
$expanded = $service->summary(34);
jobStatusAssert($expanded['remaining'] === 31 && count($expanded['groups']) === 3, 'Fan-out counts records, not the three operation types');
jobStatusAssert(str_contains($component->render()->render(), '31 عملیات'), 'Visible total follows expanding queue');
config(['queue.connections' => ['sync' => ['driver' => 'sync']], 'queue.failed.driver' => 'null']);
jobStatusAssert(!$service->summary(34)['available'], 'Unsupported backend must not claim empty queue');
echo "Supplier job status checks passed.\n";
