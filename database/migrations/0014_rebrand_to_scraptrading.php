<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Migration;

/**
 * Carry the platform's rename to a site that is already running.
 *
 * The code now takes the name from the `site_name` setting everywhere, but rows
 * already written to the database still say "ScrapX": the About, Terms, KYC and
 * commission pages, two FAQ headlines, and the platform's own business record
 * that appears on invoices. Editing each one by hand in the admin panel would be
 * tedious and easy to get half-done, so it happens here.
 *
 * In long CMS prose the name becomes the `{{site_name}}` placeholder rather than
 * a literal, so these pages follow Admin → Settings from now on and a second
 * rename needs no migration at all. Short labels — a business name that prints
 * on a tax invoice — take the literal name, because a placeholder in a legal
 * entity's name would be wrong.
 *
 * Every statement is written to be safe on a site whose operator has already
 * edited this content: a setting is only changed while it still holds the exact
 * value it shipped with, and the text replacements can only match the brand
 * string this project put there. Nothing a trader wrote is touched, and
 * re-running the migration changes nothing further.
 */
return new class extends Migration {
    /** The name the platform shipped with, and the name it ships with now. */
    private const OLD_NAME = 'ScrapX';
    private const NEW_NAME = 'Scraptrading';

    /**
     * Editable content, where the name becomes a placeholder that tracks the
     * setting. Titles and meta text are resolved in the page layout, so a
     * placeholder in them never reaches the browser.
     *
     * What is deliberately absent matters as much as what is here. Audit logs,
     * login history, bid user agents and the notification queue record what
     * actually happened or what was actually sent, and rewriting history would
     * make those records false. Email addresses are nobody's to rewrite but
     * their owner's. And `businesses.slug` stays as it is, because a slug is a
     * URL: changing it would break every link and search result pointing at the
     * platform's own profile page, which a rename is no reason to do.
     */
    private const PROSE = [
        ['cms_pages', 'content'],
        ['cms_pages', 'title'],
        ['cms_pages', 'meta_title'],
        ['cms_pages', 'meta_description'],
        ['faqs', 'question'],
        ['faqs', 'answer'],
        ['materials', 'meta_description'],
        ['materials', 'meta_title'],
        ['plans', 'description'],
        ['email_templates', 'subject'],
        ['email_templates', 'body'],
        ['notification_templates', 'title_template'],
        ['notification_templates', 'body_template'],
    ];

    public function version(): string
    {
        return '0014';
    }

    public function description(): string
    {
        return 'Rename the platform to Scraptrading in stored content';
    }

    public function up(Database $db): void
    {
        // Settings the operator has not touched. An operator who already chose
        // their own site name, from-name or meta title keeps it.
        $db->run(
            'UPDATE settings SET value = :new WHERE key_name = :k AND value = :old',
            ['new' => self::NEW_NAME, 'k' => 'site_name', 'old' => self::OLD_NAME]
        );
        $db->run(
            'UPDATE settings SET value = :new WHERE key_name = :k AND value = :old',
            ['new' => self::NEW_NAME, 'k' => 'mail_from_name', 'old' => self::OLD_NAME]
        );
        $db->run(
            'UPDATE settings SET value = :new WHERE key_name = :k AND value = :old',
            [
                'k' => 'seo_meta_title',
                'old' => 'ScrapX — B2B Scrap Trading, Auctions & RFQ Marketplace in India',
                'new' => 'Scraptrading — B2B Scrap Trading, Auctions & RFQ Marketplace in India',
            ]
        );

        // Describe the placeholder on the setting that now drives it, so an
        // administrator reading the form knows why `{{site_name}}` is there.
        $db->run(
            'UPDATE settings SET description = :d WHERE key_name = :k',
            [
                'k' => 'site_name',
                'd' => 'Shown in the header, page titles, emails and invoices. '
                    . 'CMS pages that contain {{site_name}} follow this value.',
            ]
        );

        foreach (self::PROSE as [$table, $column]) {
            $this->placeholderise($db, $table, $column);
        }

        // The platform's own business row supplies the supplier name on every
        // commission invoice, so it takes the real name, not a placeholder.
        $this->replaceExact($db, 'businesses', 'name', self::OLD_NAME . ' (Platform)', self::NEW_NAME . ' (Platform)');

        // Demo profiles created by the installer. Matched in full, so a real
        // business that happens to mention us in its own description is safe.
        $this->replaceExact(
            $db,
            'businesses',
            'about',
            'Demo business profile created by the ScrapX installer for evaluation purposes.',
            'Demo business profile created by the installer for evaluation purposes.'
        );
    }

    public function down(Database $db): void
    {
        // Deliberately irreversible. Turning `{{site_name}}` back into "ScrapX"
        // would overwrite the operator's chosen name with one they never used,
        // and a rollback of this migration is never what anyone wants: the
        // placeholder is correct under either name. Nothing to undo.
    }

    /**
     * Replace the old brand name with the `{{site_name}}` placeholder wherever it
     * appears in a long-text column, skipping tables that this installation does
     * not have (the set of content tables has grown over releases).
     */
    private function placeholderise(Database $db, string $table, string $column): void
    {
        if (!$this->hasColumn($db, $table, $column)) {
            return;
        }
        $db->run(
            sprintf(
                'UPDATE `%s` SET `%s` = REPLACE(`%s`, :old, :new) WHERE `%s` LIKE :needle',
                $db->safeTable($table),
                $db->safeColumn($column),
                $db->safeColumn($column),
                $db->safeColumn($column)
            ),
            ['old' => self::OLD_NAME, 'new' => '{{site_name}}', 'needle' => '%' . self::OLD_NAME . '%']
        );
    }

    /** Swap one exact value for another, leaving every other row alone. */
    private function replaceExact(Database $db, string $table, string $column, string $from, string $to): void
    {
        if (!$this->hasColumn($db, $table, $column)) {
            return;
        }
        $db->run(
            sprintf(
                'UPDATE `%s` SET `%s` = :to WHERE `%s` = :from',
                $db->safeTable($table),
                $db->safeColumn($column),
                $db->safeColumn($column)
            ),
            ['to' => $to, 'from' => $from]
        );
    }

    private function hasColumn(Database $db, string $table, string $column): bool
    {
        return (int) $db->scalar(
            'SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c',
            ['t' => $table, 'c' => $column],
            0
        ) > 0;
    }
};
