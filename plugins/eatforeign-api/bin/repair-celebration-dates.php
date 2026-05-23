<?php
/**
 * CLI / local HTTP: repair celebration ef_event_date values.
 *
 * From site root:
 *   php wp-content/plugins/eatforeign-api/bin/repair-celebration-dates.php
 *   php wp-content/plugins/eatforeign-api/bin/repair-celebration-dates.php --apply
 */

declare(strict_types=1);

$root = dirname( __DIR__, 4 );
if ( ! is_file( $root . '/wp-load.php' ) ) {
	fwrite( STDERR, "Could not find wp-load.php (expected site root at {$root})\n" );
	exit( 1 );
}

require $root . '/wp-load.php';

$dry_run = ! in_array( '--apply', $argv ?? [], true );
$report  = EatForeignAPI\CelebrationDateRepair::repair_all( $dry_run );

echo ( $dry_run ? "DRY RUN\n" : "APPLIED\n" );
echo "Scanned: {$report['scanned']}\n";
echo "Missing/invalid: {$report['missing']}\n";
echo "Repaired: {$report['repaired']}\n";
echo "Skipped (no date): {$report['skipped']}\n\n";

foreach ( $report['items'] as $row ) {
	if ( $row['normalized'] === '' ) {
		echo "[skip] #{$row['id']} {$row['title']}\n";
		continue;
	}
	echo "[fix] #{$row['id']} {$row['title']} → {$row['normalized']} ({$row['source']})\n";
}

exit( 0 );
