<?php

namespace CamInv\EInvoice\Tests\Unit\UBL;

use CamInv\EInvoice\UBL\XmlSanitizer;
use CamInv\EInvoice\Tests\TestCase;
use DOMDocument;

class XmlSanitizerTest extends TestCase
{
    // ──────────────────── sanitize (XML 1.0) ──────────────────

    public function test_null_returns_empty_string(): void
    {
        $this->assertSame('', XmlSanitizer::sanitize(null));
    }

    public function test_empty_string_returns_empty_string(): void
    {
        $this->assertSame('', XmlSanitizer::sanitize(''));
    }

    public function test_valid_text_passes_through(): void
    {
        $input = 'Hello World 123';
        $this->assertSame($input, XmlSanitizer::sanitize($input));
    }

    public function test_xml_special_chars_are_preserved(): void
    {
        $input = 'Price < 10 & Qty > 5 is "ok"';
        $this->assertSame($input, XmlSanitizer::sanitize($input));
    }

    public function test_valid_control_chars_are_preserved(): void
    {
        $input = "Line1\tLine2\nLine3\rLine4";
        $this->assertSame($input, XmlSanitizer::sanitize($input));
    }

    public function test_illegal_control_characters_are_stripped(): void
    {
        $input = "Hello\x00World\x01Test\x08End";
        $this->assertSame('HelloWorldTestEnd', XmlSanitizer::sanitize($input));
    }

    public function test_illegal_chars_between_x0b_and_x1f_stripped(): void
    {
        $input = "A\x0B\x0C\x1FZ";
        $this->assertSame('AZ', XmlSanitizer::sanitize($input));
    }

    public function test_multibyte_utf8_preserved(): void
    {
        $input = "\u{1797}\u{17B6}\u{179F}\u{17B6}\u{1781}\u{17D2}\u{1798}\u{17C2}\u{179A} \u{65E5}\u{672C}\u{8A9E} \u{D55C}\u{AD6D}\u{C5B4}";
        $this->assertSame($input, XmlSanitizer::sanitize($input));
    }

    public function test_emoji_are_preserved(): void
    {
        $input = 'Test \u{2705} Done';
        $this->assertSame($input, XmlSanitizer::sanitize($input));
    }

    public function test_mixed_valid_and_invalid_chars(): void
    {
        $input = "Valid\x00Text\x02With\x1FInvalid";
        $this->assertSame('ValidTextWithInvalid', XmlSanitizer::sanitize($input));
    }

    // ────────────── sanitizeForXml11 (XML 1.1) ──────────────

    public function test_xml11_allows_ctrl_chars_except_null(): void
    {
        // XML 1.1 allows characters #x1-#x1F (except NULL #x0)
        $input = "A\x01\x02\x1FZ";
        $this->assertSame($input, XmlSanitizer::sanitizeForXml11($input));
    }

    public function test_xml11_still_strips_null(): void
    {
        $input = "Hello\x00World";
        $this->assertSame('HelloWorld', XmlSanitizer::sanitizeForXml11($input));
    }

    public function test_xml11_null_empty_returns_empty(): void
    {
        $this->assertSame('', XmlSanitizer::sanitizeForXml11(null));
        $this->assertSame('', XmlSanitizer::sanitizeForXml11(''));
    }

    // ────────────── escapeEntities (smart ampersand) ──────────────

    public function test_escapes_bare_ampersand(): void
    {
        $this->assertSame('Jack &amp; Tom', XmlSanitizer::escapeEntities('Jack & Tom'));
    }

    public function test_does_not_double_encode_ampersand(): void
    {
        $this->assertSame('Jack &amp; Tom', XmlSanitizer::escapeEntities('Jack &amp; Tom'));
    }

    public function test_does_not_double_encode_lt(): void
    {
        $this->assertSame('x &lt; 10', XmlSanitizer::escapeEntities('x &lt; 10'));
    }

    public function test_does_not_double_encode_gt(): void
    {
        $this->assertSame('x &gt; 5', XmlSanitizer::escapeEntities('x &gt; 5'));
    }

    public function test_does_not_double_encode_quot(): void
    {
        $this->assertSame('&quot;hello&quot;', XmlSanitizer::escapeEntities('&quot;hello&quot;'));
    }

    public function test_does_not_double_encode_apos(): void
    {
        $this->assertSame("&apos;test&apos;", XmlSanitizer::escapeEntities("&apos;test&apos;"));
    }

    public function test_escapes_bare_lt_gt(): void
    {
        $this->assertSame('if x &lt;&gt; y', XmlSanitizer::escapeEntities('if x <> y'));
    }

    public function test_escapes_mixed_bare_and_entities(): void
    {
        $input = 'foo &amp; bar & baz &lt; qux';
        $expected = 'foo &amp; bar &amp; baz &lt; qux';
        $this->assertSame($expected, XmlSanitizer::escapeEntities($input));
    }

    public function test_preserves_numeric_entity_references(): void
    {
        $this->assertSame('&#65; &#x41;', XmlSanitizer::escapeEntities('&#65; &#x41;'));
    }

    public function test_escape_entities_null_empty(): void
    {
        $this->assertSame('', XmlSanitizer::escapeEntities(null));
        $this->assertSame('', XmlSanitizer::escapeEntities(''));
    }

    // ────────────── needsEscaping ──────────────

    public function test_needs_escaping_detects_bare_ampersand(): void
    {
        $this->assertTrue(XmlSanitizer::needsEscaping('Jack & Tom'));
    }

    public function test_needs_escaping_false_for_safe_text(): void
    {
        $this->assertFalse(XmlSanitizer::needsEscaping('Hello World'));
    }

    public function test_needs_escaping_false_for_null_empty(): void
    {
        $this->assertFalse(XmlSanitizer::needsEscaping(null));
        $this->assertFalse(XmlSanitizer::needsEscaping(''));
    }

    // ────────────── cleanup (full pass) ──────────────

    public function test_cleanup_strips_illegal_and_escapes_entities(): void
    {
        $input = "Hello\x00 & Tom";
        $expected = 'Hello &amp; Tom';
        $this->assertSame($expected, XmlSanitizer::cleanup($input));
    }

    public function test_cleanup_null_empty(): void
    {
        $this->assertSame('', XmlSanitizer::cleanup(null));
        $this->assertSame('', XmlSanitizer::cleanup(''));
    }

    // ────────────── appendTextElement ──────────────

    public function test_append_text_element_skips_null(): void
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $root = $doc->createElement('Root');
        $doc->appendChild($root);

        XmlSanitizer::appendTextElement($doc, $root, 'cbc:Name', null);

        $xml = $doc->saveXML();
        $this->assertStringContainsString('<Root/>', $xml);
    }

    public function test_append_text_element_skips_empty(): void
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $root = $doc->createElement('Root');
        $doc->appendChild($root);

        XmlSanitizer::appendTextElement($doc, $root, 'cbc:Name', '');

        $xml = $doc->saveXML();
        $this->assertStringContainsString('<Root/>', $xml);
    }

    public function test_append_text_element_sanitizes_and_appends(): void
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $root = $doc->createElement('Root');
        $doc->appendChild($root);

        XmlSanitizer::appendTextElement($doc, $root, 'cbc:Name', "Test\x00Name");

        $xml = $doc->saveXML();
        $this->assertStringContainsString('<cbc:Name>TestName</cbc:Name>', $xml);
    }

    public function test_append_text_element_escapes_xml_special_chars(): void
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $root = $doc->createElement('Root');
        $doc->appendChild($root);

        XmlSanitizer::appendTextElement($doc, $root, 'cbc:Description', 'A < B & C');

        $xml = $doc->saveXML();
        $this->assertStringContainsString('<cbc:Description>A &lt; B &amp; C</cbc:Description>', $xml);
    }

    // ────────────── appendTextElementNS ──────────────

    public function test_append_text_element_ns_adds_namespaced_element(): void
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $root = $doc->createElement('Root');
        $doc->appendChild($root);

        $ns = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';
        XmlSanitizer::appendTextElementNS($doc, $root, $ns, 'cbc:Note', 'Pay within 30 days');

        $xml = $doc->saveXML();
        $this->assertStringContainsString('<cbc:Note', $xml);
        $this->assertStringContainsString('Pay within 30 days', $xml);
    }

    public function test_append_text_element_ns_skips_null_empty(): void
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $root = $doc->createElement('Root');
        $doc->appendChild($root);

        $ns = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';
        XmlSanitizer::appendTextElementNS($doc, $root, $ns, 'cbc:Note', null);
        XmlSanitizer::appendTextElementNS($doc, $root, $ns, 'cbc:Note', '');

        $xml = $doc->saveXML();
        $this->assertStringContainsString('<Root/>', $xml);
    }

    public function test_append_text_element_ns_sanitizes_illegal_chars(): void
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $root = $doc->createElement('Root');
        $doc->appendChild($root);

        $ns = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';
        XmlSanitizer::appendTextElementNS($doc, $root, $ns, 'cbc:Note', "Note\x00Here");

        $xml = $doc->saveXML();
        $this->assertStringContainsString('NoteHere', $xml);
        $this->assertStringNotContainsString("\x00", $xml);
    }
}
