<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Order;
use App\Models\State;

/**
 * GST invoicing.
 *
 * Indian GST splits by place of supply: same state → CGST + SGST (half each),
 * different state → IGST (full rate). That single rule drives the whole
 * calculation below.
 */
final class InvoiceService
{
    /** Generate a sale invoice for an order (idempotent per order). */
    public static function generateForOrder(int $orderId, ?int $createdBy = null): array
    {
        $order = Order::detail($orderId);
        if ($order === null) {
            return ['ok' => false, 'error' => 'Order not found.'];
        }

        $existing = Database::instance()->first(
            "SELECT id, invoice_number FROM invoices WHERE order_id = :o AND invoice_type = 'sale' AND cancelled_at IS NULL",
            ['o' => $orderId]
        );
        if ($existing !== null) {
            return ['ok' => true, 'invoice_id' => (int) $existing['id'], 'message' => 'Invoice ' . $existing['invoice_number'] . ' already exists.'];
        }

        $sellerStateCode = self::stateCode($order['seller_gstin'] ?? null, $order['seller_state'] ?? null);
        $buyerStateCode = self::stateCode($order['buyer_gstin'] ?? null, $order['buyer_state'] ?? null);
        $interState = $sellerStateCode !== null && $buyerStateCode !== null && $sellerStateCode !== $buyerStateCode;

        $items = Order::items($orderId);
        if ($items === []) {
            $items = [[
                'description' => $order['listing_title'] ?? 'Scrap material',
                'hsn_code' => $order['hsn_code'] ?? null,
                'quantity' => $order['quantity'],
                'unit_code' => $order['unit_code'],
                'rate' => $order['rate'],
                'amount' => $order['subtotal'],
                'gst_rate' => $order['gst_rate'],
            ]];
        }

        $db = Database::instance();

        $invoiceId = $db->transaction(static function (Database $db) use ($order, $orderId, $items, $interState, $sellerStateCode, $buyerStateCode, $createdBy): int {
            $number = self::nextInvoiceNumber();

            $subtotal = 0.0;
            $cgstTotal = 0.0;
            $sgstTotal = 0.0;
            $igstTotal = 0.0;
            $prepared = [];

            foreach ($items as $sort => $item) {
                // The order's subtotal is authoritative (it may have been
                // recalculated after weighment), so scale the line to match.
                $amount = (float) ($item['amount'] ?? 0);
                if (count($items) === 1) {
                    $amount = (float) $order['subtotal'];
                }
                $gstRate = (float) ($item['gst_rate'] ?? $order['gst_rate']);
                $discountShare = count($items) === 1 ? (float) $order['discount'] : 0.0;
                $taxable = max(0, $amount - $discountShare);
                $tax = ($taxable * $gstRate) / 100;

                $cgst = $interState ? 0.0 : $tax / 2;
                $sgst = $interState ? 0.0 : $tax / 2;
                $igst = $interState ? $tax : 0.0;

                $subtotal += $taxable;
                $cgstTotal += $cgst;
                $sgstTotal += $sgst;
                $igstTotal += $igst;

                $prepared[] = [
                    'description' => substr((string) $item['description'], 0, 255),
                    'hsn_code' => $item['hsn_code'] ?? null,
                    'quantity' => dec($item['quantity'], 3),
                    'unit_code' => $item['unit_code'] ?? null,
                    'rate' => dec($item['rate'], 4),
                    'amount' => dec($taxable, 2),
                    'gst_rate' => dec($gstRate, 2),
                    'cgst' => dec($cgst, 2),
                    'sgst' => dec($sgst, 2),
                    'igst' => dec($igst, 2),
                    'total' => dec($taxable + $tax, 2),
                    'sort_order' => $sort,
                    'created_at' => now(),
                ];
            }

            $otherCharges = (float) $order['transport_charges'] + (float) $order['loading_charges'] + (float) $order['other_charges'];
            $rawTotal = $subtotal + $cgstTotal + $sgstTotal + $igstTotal + $otherCharges;
            $rounded = round($rawTotal);
            $roundOff = $rounded - $rawTotal;

            $invoiceId = $db->insert('invoices', [
                'invoice_number' => $number,
                'order_id' => $orderId,
                'invoice_type' => 'sale',
                'seller_id' => (int) $order['seller_id'],
                'buyer_id' => (int) $order['buyer_id'],
                'seller_name' => $order['seller_business'] ?? $order['seller_name'],
                'seller_gstin' => $order['seller_gstin'] ?? null,
                'seller_address' => trim(implode(', ', array_filter([
                    $order['seller_address'] ?? null,
                    $order['seller_city'] ?? null,
                    $order['seller_state'] ?? null,
                    $order['seller_pincode'] ?? null,
                ]))),
                'seller_state_code' => $sellerStateCode,
                'buyer_name' => $order['buyer_business'] ?? $order['buyer_name'],
                'buyer_gstin' => $order['buyer_gstin'] ?? null,
                'buyer_address' => trim(implode(', ', array_filter([
                    $order['delivery_address'] ?? $order['buyer_address'] ?? null,
                    $order['delivery_city'] ?? $order['buyer_city'] ?? null,
                    $order['delivery_state'] ?? $order['buyer_state'] ?? null,
                    $order['delivery_pincode'] ?? $order['buyer_pincode'] ?? null,
                ]))),
                'buyer_state_code' => $buyerStateCode,
                'invoice_date' => gmdate('Y-m-d'),
                'due_date' => self::dueDate((string) $order['payment_terms']),
                'subtotal' => dec($subtotal, 2),
                'cgst' => dec($cgstTotal, 2),
                'sgst' => dec($sgstTotal, 2),
                'igst' => dec($igstTotal, 2),
                'other_charges' => dec($otherCharges, 2),
                'round_off' => dec($roundOff, 2),
                'total' => dec($rounded, 2),
                'amount_in_words' => self::amountInWords($rounded),
                'payment_status' => $order['payment_status'] === 'paid' ? 'paid' : 'pending',
                'notes' => (string) SettingsService::get('invoice_terms', ''),
                'created_by' => $createdBy,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($prepared as $item) {
                $item['invoice_id'] = $invoiceId;
                $db->insert('invoice_items', $item);
            }

            return $invoiceId;
        });

        AuditService::log('invoice_generated', 'invoice', $invoiceId, null, ['order_id' => $orderId]);
        return ['ok' => true, 'invoice_id' => $invoiceId, 'message' => 'Invoice generated.'];
    }

    /** Commission invoice raised by the platform to a user. */
    public static function generateCommissionInvoice(int $userId, array $commissionIds, ?int $createdBy = null): array
    {
        if ($commissionIds === []) {
            return ['ok' => false, 'error' => 'Select at least one commission entry.'];
        }
        $db = Database::instance();
        [$where, $params] = $db->compileWhere(['id' => $commissionIds, 'user_id' => $userId]);
        $commissions = $db->select("SELECT * FROM commissions WHERE {$where} AND status IN ('pending','invoiced')", $params);
        if ($commissions === []) {
            return ['ok' => false, 'error' => 'No billable commission entries found.'];
        }

        $user = \App\Models\User::withBusiness($userId);
        $platformStateCode = (string) SettingsService::get('platform_state_code', '24');
        $userStateCode = self::stateCode($user['gstin'] ?? null, $user['state_name'] ?? null);
        $interState = $userStateCode !== null && $userStateCode !== $platformStateCode;

        $invoiceId = $db->transaction(static function (Database $db) use ($commissions, $user, $userId, $interState, $platformStateCode, $userStateCode, $createdBy): int {
            $subtotal = 0.0;
            $taxTotal = 0.0;
            $items = [];

            foreach ($commissions as $sort => $commission) {
                $amount = (float) $commission['amount'];
                $tax = (float) $commission['gst_amount'];
                $subtotal += $amount;
                $taxTotal += $tax;
                $items[] = [
                    'description' => label((string) $commission['fee_type']) . ' on order #' . ($commission['order_id'] ?? '—'),
                    'hsn_code' => '9985',
                    'quantity' => '1.000',
                    'unit_code' => 'NOS',
                    'rate' => dec($amount, 4),
                    'amount' => dec($amount, 2),
                    'gst_rate' => dec(SettingsService::get('commission_gst_rate', '18.00'), 2),
                    'cgst' => dec($interState ? 0 : $tax / 2, 2),
                    'sgst' => dec($interState ? 0 : $tax / 2, 2),
                    'igst' => dec($interState ? $tax : 0, 2),
                    'total' => dec($amount + $tax, 2),
                    'sort_order' => $sort,
                    'created_at' => now(),
                ];
            }

            $rawTotal = $subtotal + $taxTotal;
            $rounded = round($rawTotal);

            $invoiceId = $db->insert('invoices', [
                'invoice_number' => self::nextInvoiceNumber('COM'),
                'invoice_type' => 'commission',
                'seller_id' => (int) $db->scalar('SELECT id FROM users ORDER BY id LIMIT 1', [], 1),
                'buyer_id' => $userId,
                'seller_name' => (string) SettingsService::get('site_name', 'ScrapX'),
                'seller_gstin' => (string) SettingsService::get('platform_gstin', ''),
                'seller_address' => (string) SettingsService::get('contact_address', ''),
                'seller_state_code' => $platformStateCode,
                'buyer_name' => $user['business_name'] ?? ($user['full_name'] ?? 'Customer'),
                'buyer_gstin' => $user['gstin'] ?? null,
                'buyer_address' => trim((string) (($user['city_name'] ?? '') . ' ' . ($user['state_name'] ?? ''))),
                'buyer_state_code' => $userStateCode,
                'invoice_date' => gmdate('Y-m-d'),
                'due_date' => gmdate('Y-m-d', strtotime('+7 days')),
                'subtotal' => dec($subtotal, 2),
                'cgst' => dec($interState ? 0 : $taxTotal / 2, 2),
                'sgst' => dec($interState ? 0 : $taxTotal / 2, 2),
                'igst' => dec($interState ? $taxTotal : 0, 2),
                'round_off' => dec($rounded - $rawTotal, 2),
                'total' => dec($rounded, 2),
                'amount_in_words' => self::amountInWords($rounded),
                'payment_status' => 'pending',
                'created_by' => $createdBy,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($items as $item) {
                $item['invoice_id'] = $invoiceId;
                $db->insert('invoice_items', $item);
            }

            foreach ($commissions as $commission) {
                $db->update('commissions', ['status' => 'invoiced', 'updated_at' => now()], ['id' => (int) $commission['id']]);
            }

            return $invoiceId;
        });

        return ['ok' => true, 'invoice_id' => $invoiceId, 'message' => 'Commission invoice generated.'];
    }

    public static function detail(int $invoiceId): ?array
    {
        $invoice = Database::instance()->first(
            'SELECT i.*, o.reference AS order_reference, o.status AS order_status
             FROM invoices i LEFT JOIN orders o ON o.id = i.order_id WHERE i.id = :id',
            ['id' => $invoiceId]
        );
        if ($invoice === null) {
            return null;
        }
        $invoice['items'] = Database::instance()->select(
            'SELECT * FROM invoice_items WHERE invoice_id = :i ORDER BY sort_order, id',
            ['i' => $invoiceId]
        );
        return $invoice;
    }

    public static function forOrder(int $orderId): array
    {
        return Database::instance()->select(
            'SELECT * FROM invoices WHERE order_id = :o ORDER BY id DESC',
            ['o' => $orderId]
        );
    }

    public static function cancel(int $invoiceId, string $reason): array
    {
        Database::instance()->update('invoices', [
            'cancelled_at' => now(),
            'payment_status' => 'cancelled',
            'notes' => 'Cancelled: ' . substr($reason, 0, 200),
            'updated_at' => now(),
        ], ['id' => $invoiceId]);
        AuditService::log('invoice_cancelled', 'invoice', $invoiceId, null, ['reason' => $reason]);
        return ['ok' => true, 'message' => 'Invoice cancelled.'];
    }

    /** Sequential per financial year: PREFIX/2026-27/000123 */
    private static function nextInvoiceNumber(string $prefixOverride = ''): string
    {
        $prefix = $prefixOverride !== '' ? $prefixOverride : (string) SettingsService::get('invoice_prefix', 'INV');
        $month = (int) gmdate('n');
        $year = (int) gmdate('Y');
        $startYear = $month >= 4 ? $year : $year - 1;
        $financialYear = $startYear . '-' . substr((string) ($startYear + 1), 2);

        $pattern = $prefix . '/' . $financialYear . '/%';
        $last = Database::instance()->scalar(
            'SELECT invoice_number FROM invoices WHERE invoice_number LIKE :p ORDER BY id DESC LIMIT 1',
            ['p' => $pattern]
        );

        $next = (int) SettingsService::get('invoice_start_number', 1);
        if ($last !== null && preg_match('#/(\d+)$#', (string) $last, $m)) {
            $next = (int) $m[1] + 1;
        }

        return sprintf('%s/%s/%06d', $prefix, $financialYear, $next);
    }

    /** GSTIN's first two digits are the state code; fall back to the state name. */
    private static function stateCode(?string $gstin, ?string $stateName): ?string
    {
        if ($gstin !== null && strlen($gstin) >= 2 && ctype_digit(substr($gstin, 0, 2))) {
            return substr($gstin, 0, 2);
        }
        if ($stateName !== null && $stateName !== '') {
            foreach (State::active() as $state) {
                if (strcasecmp((string) $state['name'], $stateName) === 0) {
                    return $state['gst_state_code'];
                }
            }
        }
        return null;
    }

    private static function dueDate(string $paymentTerms): ?string
    {
        return match ($paymentTerms) {
            'credit_7' => gmdate('Y-m-d', strtotime('+7 days')),
            'credit_15' => gmdate('Y-m-d', strtotime('+15 days')),
            'credit_30' => gmdate('Y-m-d', strtotime('+30 days')),
            'advance' => gmdate('Y-m-d'),
            default => gmdate('Y-m-d', strtotime('+3 days')),
        };
    }

    /** Indian numbering for the "Amount in words" line on the invoice. */
    public static function amountInWords(float $amount): string
    {
        $rupees = (int) floor($amount);
        $paise = (int) round(($amount - $rupees) * 100);

        $words = self::numberToWords($rupees) . ' Rupees';
        if ($paise > 0) {
            $words .= ' and ' . self::numberToWords($paise) . ' Paise';
        }
        return $words . ' Only';
    }

    private static function numberToWords(int $number): string
    {
        if ($number === 0) {
            return 'Zero';
        }
        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
            'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

        $chunk = static function (int $n) use ($ones, $tens): string {
            $text = '';
            if ($n >= 100) {
                $text .= $ones[intdiv($n, 100)] . ' Hundred ';
                $n %= 100;
            }
            if ($n >= 20) {
                $text .= $tens[intdiv($n, 10)] . ' ';
                $n %= 10;
            }
            if ($n > 0) {
                $text .= $ones[$n] . ' ';
            }
            return $text;
        };

        $parts = [];
        // Indian grouping: crore, lakh, thousand, hundred.
        if ($number >= 10000000) {
            $parts[] = $chunk(intdiv($number, 10000000)) . 'Crore';
            $number %= 10000000;
        }
        if ($number >= 100000) {
            $parts[] = $chunk(intdiv($number, 100000)) . 'Lakh';
            $number %= 100000;
        }
        if ($number >= 1000) {
            $parts[] = $chunk(intdiv($number, 1000)) . 'Thousand';
            $number %= 1000;
        }
        if ($number > 0) {
            $parts[] = trim($chunk($number));
        }

        return trim(preg_replace('/\s+/', ' ', implode(' ', $parts)) ?? '');
    }
}
