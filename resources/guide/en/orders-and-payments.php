<?php

declare(strict_types=1);

/** Help Centre — Orders, weighment, payment and invoicing. */
return [
    'title' => 'Orders, weighment and payment',
    'summary' => 'From an accepted deal to a settled, invoiced and reviewed order — including how actual weight decides the money.',
    'icon' => 'bi-bag-check',
    'audience' => 'everyone',
    'articles' => [

        [
            'slug' => 'the-order-lifecycle',
            'title' => 'The order lifecycle',
            'summary' => 'Every stage an order passes through, who moves it, and what each one means.',
            'minutes' => 6,
            'keywords' => ['order', 'status', 'lifecycle', 'stages', 'confirmed', 'delivered', 'completed'],
            'body' => [
                ['type' => 'p', 'text' => 'An order is created automatically the moment a deal is agreed — a fixed-price listing accepted, an offer accepted, an auction awarded, or an RFQ awarded. It carries the agreed material, quantity, rate and terms, and both sides work from the same record.'],
                ['type' => 'link', 'to' => 'dashboard/orders', 'text' => 'My orders', 'icon' => 'bi-bag-check'],

                ['type' => 'h', 'text' => 'The stages'],
                ['type' => 'table', 'head' => ['Stage', 'What it means', 'Who moves it on'], 'rows' => [
                    ['Pending', 'Created, not yet confirmed by both sides.', 'Either'],
                    ['Confirmed', 'Both parties are committed to the deal.', 'Seller'],
                    ['Processing', 'The seller is preparing the material — sorting, baling, loading.', 'Seller'],
                    ['Ready for pickup', 'Material is ready at the yard.', 'Seller'],
                    ['Picked up', 'The vehicle has been loaded and left.', 'Seller'],
                    ['In transit', 'On the road. Transport details are on the order.', 'Either'],
                    ['Delivered', 'Arrived at the buyer\'s point.', 'Buyer'],
                    ['Weighment', 'The weighbridge figure is being recorded and agreed.', 'Either'],
                    ['Payment pending', 'Settled amount is agreed; payment is due.', 'Buyer'],
                    ['Paid', 'Payment recorded and confirmed by the seller.', 'Seller confirms'],
                    ['Completed', 'Everything done. Reviews can be left.', 'Either'],
                ]],
                ['type' => 'p', 'text' => 'Two stages sit outside the normal path: <strong>Cancelled</strong>, when the deal is abandoned by agreement, and <strong>Disputed</strong>, when one side raises a dispute — see <em>Raise a dispute</em>.'],

                ['type' => 'h', 'text' => 'How stages move'],
                ['type' => 'p', 'text' => 'Stages can only move in a valid order — you cannot jump from Confirmed to Paid. Every change is written to the order\'s history with who made it and when, so there is always a record of what happened in what sequence.'],
                ['type' => 'tip', 'text' => 'Update the stage as things actually happen rather than at the end. The counterparty sees it immediately and stops phoning to ask.'],

                ['type' => 'h', 'text' => 'What is on the order page'],
                ['type' => 'list', 'items' => [
                    'The agreed material, quantity, rate and terms.',
                    'Both parties\' details, including the bank details needed to pay.',
                    'The stage history.',
                    'Weighment records.',
                    'Transport and delivery details.',
                    'Payments recorded against the order.',
                    'The invoice, once raised.',
                    'The message thread for this deal.',
                ]],
            ],
        ],

        [
            'slug' => 'record-the-weighment',
            'title' => 'Record the weighment — how the final amount is decided',
            'summary' => 'The core of the platform. Material is sold on estimate and paid on the weighbridge slip. This is how that is captured and agreed.',
            'minutes' => 8,
            'keywords' => ['weighment', 'weighbridge', 'tare', 'gross', 'net weight', 'deduction', 'settlement', 'moisture', 'weighbridge slip', 'short weight', 'kaanta', 'dharmakanta'],
            'body' => [
                ['type' => 'p', 'text' => 'Scrap is quoted on an estimate and paid for on fact. The weighment record is where that difference is written down, calculated and agreed by both sides — instead of being argued about afterwards.'],

                ['type' => 'h', 'text' => 'What to enter'],
                ['type' => 'settings', 'intro' => 'From the weighbridge slip:', 'rows' => [
                    ['Gross weight (kg)', 'The loaded vehicle on the weighbridge.'],
                    ['Tare weight (kg)', 'The empty vehicle.'],
                    ['Actual (net) weight (kg)', 'Gross minus tare — the material itself. This is the figure the money is calculated from.'],
                    ['Deduction amount (₹)', 'Any agreed reduction for moisture, mud, non-metallic content or contamination. Leave at zero if there is none.'],
                    ['Deduction reason', 'Always fill this in when there is a deduction. An unexplained deduction is the commonest cause of a dispute.'],
                    ['Weighbridge name', 'Which weighbridge. A neutral, licensed one carries more weight in a dispute than the seller\'s own.'],
                    ['Slip number', 'The serial from the slip.'],
                    ['Vehicle number', 'The truck that was weighed.'],
                    ['Weighed at', 'Date and time on the slip.'],
                    ['Slip and photographs', 'Upload the slip itself, and a photograph of the load. This is the evidence if anything is questioned later.'],
                ]],

                ['type' => 'h', 'text' => 'How the settled amount is worked out'],
                ['type' => 'p', 'text' => 'The platform does the arithmetic and shows every step, so both sides can check it:'],
                ['type' => 'code', 'text' => "rate per kg      = agreed order value ÷ expected weight\nsettled amount   = (actual weight × rate per kg) − deduction"],
                ['type' => 'p', 'text' => 'A worked example. A 20 MT lot sold for ₹6,15,000; the weighbridge says 19,450 kg; ₹8,000 deducted for moisture:'],
                ['type' => 'table', 'head' => ['Figure', 'Value', 'How'], 'rows' => [
                    ['Expected weight', '20,000.000 kg', '20 MT as listed'],
                    ['Actual weight', '19,450.000 kg', 'gross − tare, from the slip'],
                    ['Difference', '−550.000 kg (−2.750%)', 'short by just under 3%'],
                    ['Rate per kg', '₹30.7500', '6,15,000 ÷ 20,000'],
                    ['Deduction', '₹8,000.00', 'moisture, as agreed'],
                    ['<strong>Settled amount</strong>', '<strong>₹5,90,087.50</strong>', '19,450 × 30.75 − 8,000'],
                ]],
                ['type' => 'p', 'text' => 'Once accepted, the order is re-priced to the settled figure, GST is recalculated on it, and platform commission is charged on the <strong>settled</strong> value — not on the original estimate. The invoice is raised on the settled amount too.'],

                ['type' => 'h', 'text' => 'Agreeing it'],
                ['type' => 'steps', 'items' => [
                    ['title' => 'One side records the weighment', 'text' => 'Usually whoever is standing at the weighbridge. Attach the slip.'],
                    ['title' => 'The other side is notified', 'text' => 'They see the full calculation, not just the total.'],
                    ['title' => 'They accept, or dispute it', 'text' => 'Accepting fixes the settled amount and moves the order to payment. Disputing holds the order and opens the question.'],
                ]],
                ['type' => 'table', 'head' => ['Weighment state', 'Meaning'], 'rows' => [
                    ['Recorded', 'Entered, waiting for the other side.'],
                    ['Accepted', 'Agreed. The order is re-priced to this figure.'],
                    ['Disputed', 'The other side disagrees. Resolve it in the thread or raise a formal dispute.'],
                    ['Revised', 'Re-entered after a correction — for example a re-weigh on a different bridge.'],
                ]],

                ['type' => 'h', 'text' => 'Avoiding weighment arguments'],
                ['type' => 'list', 'items' => [
                    '<strong>Agree the weighbridge before the truck moves.</strong> Not after it has been weighed.',
                    '<strong>Agree the deduction basis up front.</strong> "Moisture deduction at actuals, assessed jointly" is a sentence worth putting in the message thread before loading.',
                    '<strong>Photograph everything.</strong> The slip, the load, the vehicle number plate. It takes a minute and settles most questions instantly.',
                    '<strong>Record it on the day.</strong> A weighment entered a week later with no slip attached is very hard to defend.',
                ]],
                ['type' => 'warning', 'text' => 'Do not accept a weighment you have not actually checked. Acceptance re-prices the order and is the figure the invoice and commission are based on.'],
            ],
        ],

        [
            'slug' => 'arrange-transport',
            'title' => 'Arrange transport',
            'summary' => 'Recording the vehicle, driver and delivery against the order so both sides can see where the load is.',
            'minutes' => 3,
            'keywords' => ['transport', 'logistics', 'delivery', 'vehicle', 'driver', 'lr', 'freight'],
            'body' => [
                ['type' => 'p', 'text' => 'Transport is recorded on the order so both parties — and the platform, if anything is disputed — can see what moved, when and in which vehicle.'],
                ['type' => 'link', 'to' => 'dashboard/transport', 'text' => 'Transport', 'icon' => 'bi-truck'],

                ['type' => 'h', 'text' => 'What to record'],
                ['type' => 'list', 'items' => [
                    'Who is arranging it — seller, buyer, or a transporter.',
                    'Vehicle number, and the driver\'s name and mobile.',
                    'Pickup and expected delivery dates.',
                    'Freight cost and who bears it.',
                    'The LR or consignment note number, and the e-way bill where one is required.',
                ]],

                ['type' => 'h', 'text' => 'Who pays freight'],
                ['type' => 'p', 'text' => 'Settle this in writing before the vehicle is booked. It is the second commonest dispute after weighment, and it is entirely avoidable. Put it in the order\'s message thread.'],
                ['type' => 'tip', 'text' => 'Transporters are listed in the Businesses directory under the transporter type. If you do not have one on a route, look there.'],
                ['type' => 'note', 'text' => 'The platform records transport details; it does not book vehicles or track them live by GPS.'],
            ],
        ],

        [
            'slug' => 'record-and-confirm-payment',
            'title' => 'Record and confirm payment',
            'summary' => 'How money is recorded, what the platform does and does not handle, and how the seller confirms receipt.',
            'minutes' => 5,
            'keywords' => ['payment', 'pay', 'utr', 'neft', 'rtgs', 'upi', 'razorpay', 'bank transfer', 'confirm', 'not received payment', 'utr number'],
            'body' => [
                ['type' => 'warning', 'text' => 'By default the platform <strong>does not hold your money</strong>. Buyer and seller settle directly — bank transfer, UPI, cheque, cash — and the payment is <em>recorded</em> against the order so both sides and the invoice agree on what was paid. Escrow and wallet features exist and can be switched on by the operator, but are off unless they say otherwise.'],

                ['type' => 'h', 'text' => 'The buyer records the payment'],
                ['type' => 'steps', 'items' => [
                    ['title' => 'Open the order', 'text' => 'The seller\'s bank details and UPI ID are shown on a confirmed order.'],
                    ['title' => 'Pay', 'text' => 'Outside the platform, by whatever method you agreed.'],
                    ['title' => 'Record it', 'text' => 'Amount, method, date, and the reference — the UTR for NEFT or RTGS, the transaction ID for UPI, the cheque number. The reference is what lets the seller find it in their statement, so get it right.'],
                    ['title' => 'Attach the proof', 'text' => 'A screenshot or the bank advice. Optional, but it removes nearly all follow-up questions.'],
                ]],
                ['type' => 'p', 'text' => 'Methods available: UPI, NEFT, RTGS, IMPS, bank transfer, cheque, cash, credit, wallet, or an online gateway where the operator has configured one.'],

                ['type' => 'h', 'text' => 'The seller confirms receipt'],
                ['type' => 'p', 'text' => 'A recorded payment is not a received payment. Check your bank, then confirm it on the order. Only then does the order move to <strong>Paid</strong>.'],
                ['type' => 'warning', 'text' => 'Never confirm a payment you have not seen credited in your own account. A screenshot is not money.'],

                ['type' => 'h', 'text' => 'Part payments'],
                ['type' => 'p', 'text' => 'Advances and part payments are supported — record each one separately. The order shows the running total and the balance, and the payment status reads <em>Partial</em> until the full amount is received.'],

                ['type' => 'h', 'text' => 'Online payment'],
                ['type' => 'p', 'text' => 'Where the operator has configured a payment gateway, a <strong>Pay now</strong> option appears and payment is confirmed automatically. If no gateway is configured, the platform says so plainly rather than offering a button that does nothing.'],
                ['type' => 'link', 'to' => 'dashboard/payments', 'text' => 'My payments', 'icon' => 'bi-cash-coin'],

                ['type' => 'h', 'text' => 'Platform commission'],
                ['type' => 'p', 'text' => 'Where commission is enabled, it is calculated on the <strong>settled</strong> order value after weighment, plus GST, and shown on the order before confirmation and in your commission ledger. There are no charges that appear only after the fact.'],
            ],
        ],

        [
            'slug' => 'understand-your-gst-invoice',
            'title' => 'Understand your GST invoice',
            'summary' => 'How CGST+SGST and IGST are decided, how numbering works, and where to get the printable copy.',
            'minutes' => 5,
            'keywords' => ['invoice', 'gst', 'cgst', 'sgst', 'igst', 'tax', 'hsn', 'print', 'amount in words'],
            'body' => [
                ['type' => 'p', 'text' => 'An invoice is raised on the <strong>settled</strong> amount — the figure from the weighment, not the original estimate.'],

                ['type' => 'h', 'text' => 'Which tax applies'],
                ['type' => 'p', 'text' => 'Worked out from the first two digits of each party\'s GSTIN, which encode the state:'],
                ['type' => 'table', 'head' => ['Situation', 'Tax charged'], 'rows' => [
                    ['Seller and buyer in the same state', 'CGST + SGST, half the rate each'],
                    ['Seller and buyer in different states', 'IGST at the full rate'],
                ]],
                ['type' => 'p', 'text' => 'This is why your GSTIN has to be on your business profile before an invoice can be raised — without it the platform cannot tell which tax applies.'],

                ['type' => 'h', 'text' => 'What is on the invoice'],
                ['type' => 'list', 'items' => [
                    'Invoice number and date. Numbering is sequential per Indian financial year, with the prefix the operator has set.',
                    'Both parties\' names, addresses, GSTINs and state codes.',
                    'Material description with its HSN code.',
                    'Quantity (the settled weight), rate, and taxable value.',
                    'CGST and SGST, or IGST, with the rate and amount.',
                    'Rounding off, and the total payable.',
                    'The amount in words, in Indian lakh and crore grouping.',
                    'The terms and footer set by the operator.',
                ]],

                ['type' => 'h', 'text' => 'Getting a copy'],
                ['type' => 'p', 'text' => 'Open the order and choose the invoice. There is a print view laid out for A4 — use your browser\'s print dialogue and "Save as PDF" to keep or email a copy.'],
                ['type' => 'tip', 'text' => 'Check the invoice the day it is raised. Corrections are straightforward now and awkward after your GST return is filed.'],
                ['type' => 'note', 'text' => 'The platform produces a tax invoice from the order data. It does not file your returns and does not generate e-invoices or e-way bills.'],
            ],
        ],

        [
            'slug' => 'leave-and-read-reviews',
            'title' => 'Leave and read reviews',
            'summary' => 'How ratings are built, and why they are the most valuable asset on your account.',
            'minutes' => 3,
            'keywords' => ['review', 'rating', 'feedback', 'reputation', 'stars'],
            'body' => [
                ['type' => 'p', 'text' => 'When an order completes, both sides can review each other. Reviews can only be left against a completed order, so every rating on this platform comes from a real deal.'],

                ['type' => 'h', 'text' => 'Leaving a useful review'],
                ['type' => 'list', 'items' => [
                    'Rate the specific things — material as described, weight as expected, communication, payment or pickup on time — not just an overall mood.',
                    'Write a line or two of fact. "Grade was as photographed, loaded same day, 2% short on weight which was agreed" tells the next trader more than five stars on their own.',
                    'Review promptly, while you remember the detail.',
                ]],

                ['type' => 'h', 'text' => 'Reading reviews'],
                ['type' => 'p', 'text' => 'A business profile shows its average rating, how many reviews it has, and how many orders it has completed. Read the <em>words</em>, not only the stars — and weigh the number of completed orders. A 4.6 across forty deals is a stronger signal than a 5.0 across two.'],
                ['type' => 'p', 'text' => 'Ratings feed search placement, so they compound: good reviews bring more enquiries, which bring more reviews.'],
                ['type' => 'link', 'to' => 'dashboard/reviews', 'text' => 'My reviews', 'icon' => 'bi-star'],
            ],
        ],

        [
            'slug' => 'raise-a-dispute',
            'title' => 'Raise a dispute',
            'summary' => 'When a deal goes wrong, how to escalate it properly and what the platform can and cannot do.',
            'minutes' => 5,
            'keywords' => ['dispute', 'problem', 'complaint', 'escalate', 'resolution', 'evidence'],
            'body' => [
                ['type' => 'p', 'text' => 'Most problems are solved in the order\'s message thread in an hour. Try that first — a dispute is slower than a phone call and a clear message.'],
                ['type' => 'p', 'text' => 'Raise a formal dispute when the other side has stopped responding, or when you genuinely cannot agree.'],

                ['type' => 'h', 'text' => 'How to raise one'],
                ['type' => 'steps', 'items' => [
                    ['title' => 'Open the order and choose Raise a dispute', 'text' => 'The dispute is attached to the order, so the platform can see the whole history without you re-explaining it.'],
                    ['title' => 'Pick the category', 'text' => 'Quality, quantity or weight, payment, delivery, or other. This decides who reviews it.'],
                    ['title' => 'State what happened, plainly', 'text' => 'Dates, figures, what was agreed and what actually happened. Keep it factual — a reviewer reading a calm account acts faster than one reading an argument.'],
                    ['title' => 'Attach your evidence', 'text' => 'Weighbridge slips, photographs, payment advice, the message thread. Evidence decides disputes; assertions do not.'],
                    ['title' => 'Say what you want', 'text' => 'A specific outcome — a price adjustment of a stated amount, a return, the balance paid. "Do something" cannot be acted on.'],
                ]],
                ['type' => 'link', 'to' => 'dashboard/disputes', 'text' => 'My disputes', 'icon' => 'bi-shield-check'],

                ['type' => 'h', 'text' => 'What happens next'],
                ['type' => 'table', 'head' => ['State', 'Meaning'], 'rows' => [
                    ['Open', 'Raised, awaiting review.'],
                    ['Under review', 'A member of the platform team is looking at it.'],
                    ['Evidence requested', 'More information is needed from one or both sides. Respond quickly — this is the stage disputes stall at.'],
                    ['Resolved', 'A resolution has been recorded, with the reasoning.'],
                    ['Rejected', 'Not upheld. The reason is given.'],
                    ['Escalated', 'Referred to senior staff.'],
                    ['Closed', 'Concluded.'],
                ]],

                ['type' => 'h', 'text' => 'What the platform can and cannot do'],
                ['type' => 'list', 'items' => [
                    '<strong>Can</strong> — examine the order, weighment, messages and evidence; record a resolution; adjust platform commission; act against an account that is trading in bad faith.',
                    '<strong>Cannot</strong> — move money it never held, force a party to pay, or act as a court. The platform introduces buyers and sellers; unless stated otherwise it is not a party to the contract between them.',
                ]],
                ['type' => 'tip', 'text' => 'The best protection is preventive: agree the weighbridge, the deduction basis and who pays freight <em>in the message thread</em> before the truck moves. Nearly every dispute traces back to one of those three being left vague.'],
            ],
        ],
    ],
];
