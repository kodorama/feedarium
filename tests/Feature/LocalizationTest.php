<?php

describe('Localization', function () {
    it('feeds lang file has all required keys', function () {
        $keys = ['created', 'updated', 'deleted', 'not_found', 'validation'];
        foreach ($keys as $key) {
            expect(trans("feeds.{$key}"))->not->toBeEmpty();
        }
    });

    it('news lang file has all required keys', function () {
        $keys = ['created', 'updated', 'deleted', 'saved', 'unsaved', 'not_found'];
        foreach ($keys as $key) {
            expect(trans("news.{$key}"))->not->toBeEmpty();
        }
    });

    it('categories lang file has all required keys', function () {
        $keys = ['created', 'updated', 'deleted', 'not_found'];
        foreach ($keys as $key) {
            expect(trans("categories.{$key}"))->not->toBeEmpty();
        }
    });

    it('general lang file has all required keys', function () {
        $keys = ['save', 'cancel', 'delete', 'edit', 'search', 'loading'];
        foreach ($keys as $key) {
            expect(trans("general.{$key}"))->not->toBeEmpty();
        }
    });
});
