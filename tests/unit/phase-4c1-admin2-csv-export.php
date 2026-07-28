<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/autoload.php';

use Grav\Plugin\GoosializeLeads\Admin\LeadCsvExporter;
use Grav\Plugin\GoosializeLeads\Admin\LeadIndexCollection;
use Grav\Plugin\GoosializeLeads\Admin\LeadSummary;

function check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function summary(int $number, ?string $name = 'Ada', ?string $email = 'ada@example.test'): LeadSummary
{
    return LeadSummary::fromRecord([
        'id' => str_pad(dechex($number), 32, '0', STR_PAD_LEFT),
        'created_at' => sprintf('2026-07-29T12:00:%02d.000000Z', $number % 60),
        'full_name' => $name,
        'email' => $email,
        'source' => 'website',
        'form_name' => 'contact',
        'status' => 'new',
    ]);
}

$api = new ReflectionClass(LeadCsvExporter::class);
check($api->isFinal(), 'exporter not final');
check($api->getConstructor()?->isPublic() === true && $api->getConstructor()?->getNumberOfParameters() === 0, 'constructor drift');
$method = $api->getMethod('export');
check($method->isPublic() && $method->getReturnType()?->getName() === 'string', 'export API drift');

$exporter = new LeadCsvExporter();
$header = '"Lead ID","Created (UTC)","Name","Email","Source","Form","Status"' . "\r\n";
check($exporter->export(LeadIndexCollection::create([], false, 0), 131072) === $header, 'empty CSV');

$special = summary(1, " Zoë,\r\n\"=CEO\"", null);
$csv = $exporter->export(LeadIndexCollection::create([$special], false, 1), 131072);
check(!str_starts_with($csv, "\xEF\xBB\xBF"), 'unexpected BOM');
check(str_contains($csv, '" Zoë,' . "\n" . '""=CEO"""'), 'multiline/quote/unicode encoding');
check(str_ends_with($csv, "\r\n") && !str_contains(str_replace("\r\n", '', $csv), "\r"), 'line endings');
check(str_contains($csv, ',"",'), 'null representation');

foreach (['=x', '+x', '-x', '@x', "\tx", "\rx", "\nx", '  =x', " \t+x"] as $index => $dangerous) {
    $body = $exporter->export(LeadIndexCollection::create([summary($index + 2, $dangerous)], false, 1), 131072);
    check(str_contains($body, "\"'" . str_replace(["\r\n", "\r"], "\n", $dangerous) . '"'), 'formula protection');
}

$maximum = str_repeat('😀', 256);
$maximumCsv = $exporter->export(LeadIndexCollection::create([summary(19, $maximum)], false, 1), 131072);
check(str_contains($maximumCsv, $maximum), 'exact cell limit rejected');

try {
    $exporter->export(LeadIndexCollection::create([summary(20, str_repeat('x', 257))], false, 1), 131072);
    throw new RuntimeException('long cell accepted');
} catch (RuntimeException $error) {
    check($error->getMessage() === 'Invalid Lead CSV export record.', 'long-cell mapping');
}

$many = [];
for ($i = 1; $i <= 100; $i++) $many[] = summary($i, str_repeat('😀', 256), str_repeat('😀', 320));
try {
    $exporter->export(LeadIndexCollection::create($many, false, 100), 131072);
    throw new RuntimeException('response overflow accepted');
} catch (LengthException $error) {
    check($error->getMessage() === 'Lead CSV export exceeds the response limit.', 'response limit mapping');
}

$ordered = $exporter->export(LeadIndexCollection::create([summary(2), summary(1)], false, 2), 131072);
check(strpos($ordered, str_pad('2', 32, '0', STR_PAD_LEFT)) < strpos($ordered, str_pad('1', 32, '0', STR_PAD_LEFT)), 'collection order changed');
check($ordered === $exporter->export(LeadIndexCollection::create([summary(2), summary(1)], false, 2), 131072), 'nondeterministic CSV');

echo "PASS_PHASE_4C1_CSV_FORMAT\n";
echo "PASS_PHASE_4C1_CSV_INJECTION\n";
echo "PASS_PHASE_4C1_BOUNDED_EXPORT\n";
echo "PASS_PHASE_4C1_EXPORT_RESPONSES\n";
echo "PASS_PHASE_4C1_CELL_LENGTH_REJECTION\n";
