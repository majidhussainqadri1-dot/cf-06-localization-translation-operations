<?php

declare(strict_types=1);
/**
 * CF-06 fresh adversarial source review. Forty distinct executable checks;
 * local pure validators only, no provider calls or live WordPress/DB access.
 */
require __DIR__ . '/bootstrap.php';

use Sabri\Localization\Domain\Translation\MarkupValidator;
use Sabri\Localization\Domain\Translation\MessageFormatValidator;

$checks = [
    ['M01','plain text remains valid','allow','Hello','سلام'],
    ['M02','basic strong element remains valid','allow','<strong>Hello</strong>','<strong>سلام</strong>'],
    ['M03','nested structure remains valid','allow','<p><em>Read</em></p>','<p><em>پڑھیں</em></p>'],
    ['M04','void BR remains valid','allow','First<br>Next','پہلا<br>دوسرا'],
    ['M05','approved exact HTTPS href remains valid','allow','<a href="https://example.com/help">Help</a>','<a href="https://example.com/help">مدد</a>'],
    ['M06','exact root-relative href remains valid','allow','<a href="/guide">Guide</a>','<a href="/guide">رہنمائی</a>'],
    ['M07','exact fragment href remains valid','allow','<a href="#more">More</a>','<a href="#more">مزید</a>'],
    ['M08','attributes reorder without semantic change','allow','<a title="hint" href="https://example.com">Read</a>','<a href="https://example.com" title="hint">پڑھیں</a>'],
    ['M09','equivalent single and double quotes valid','allow',"<a href='https://example.com'>Read</a>",'<a href="https://example.com">پڑھیں</a>'],
    ['M10','localized lang and direction allowed with verified structure','allow','<span dir="ltr" lang="en">Text</span>','<span lang="ur" dir="rtl">متن</span>'],
    ['M11','changed HTTPS link is denied','deny','<a href="https://example.com/a">A</a>','<a href="https://attacker.example/a">B</a>'],
    ['M12','script-protocol href is denied','deny','<a href="https://example.com">A</a>','<a href="javascript:alert(1)">B</a>'],
    ['M13','entity encoded script protocol is denied','deny','<a href="https://example.com">A</a>','<a href="javascript&#58;alert(1)">B</a>'],
    ['M14','data URI href is denied','deny','<a href="https://example.com">A</a>','<a href="data:text/html,bad">B</a>'],
    ['M15','protocol-relative external href is denied','deny','<a href="/help">A</a>','<a href="//evil.example">B</a>'],
    ['M16','ftp URI is denied','deny','<a href="https://example.com">A</a>','<a href="ftp://example.com">B</a>'],
    ['M17','insecure HTTP URI is denied','deny','<a href="https://example.com">A</a>','<a href="http://example.com">B</a>'],
    ['M18','changed class attribute is denied','deny','<span class="safe">A</span>','<span class="hidden">B</span>'],
    ['M19','translated title attribute allowed and bounded','allow','<span title="Help">A</span>','<span title="مدد">B</span>'],
    ['M20','script event handler attribute is denied','deny','<span>A</span>','<span onclick="alert(1)">B</span>'],
    ['M21','inline CSS attribute is denied','deny','<span>A</span>','<span style="display:none">B</span>'],
    ['M22','unapproved data attribute is denied','deny','<span>A</span>','<span data-token="x">B</span>'],
    ['M23','unapproved IMG tag is denied','deny','<span>A</span>','<img src="x">'],
    ['M24','unbalanced tags are denied','deny','<strong>A</strong>','<strong>B'],
    ['M25','duplicate href attributes are denied','deny','<a href="/a">A</a>','<a href="/a" href="/b">B</a>'],
    ['M26','unquoted href attribute is denied','deny','<a href="/a">A</a>','<a href=/a>B</a>'],
    ['M27','href outside anchor is denied','deny','<span>A</span>','<span href="/a">B</span>'],
    ['M28','invalid dir attribute is denied','deny','<span dir="rtl">A</span>','<span dir="autoevil">B</span>'],
    ['I29','same ICU argument allowed','allow','Hello {name}','سلام {name}'],
    ['I30','ICU plural branch reorder allowed','allow','{n, plural, one {one} other {many}}','{n, plural, other {بہت} one {ایک}}'],
    ['I31','ICU select branch reorder allowed','allow','{state, select, ready {Yes} other {No}}','{state, select, other {نہیں} ready {ہاں}}'],
    ['I32','unchanged number formatter allowed','allow','Value {x, number}','قدر {x, number}'],
    ['I33','nested ICU selector allowed','allow','{n, plural, one {{name} one} other {{name} many}}','{n, plural, other {{name} کئی} one {{name} ایک}}'],
    ['I34','duplicate ICU one branch denied','deny','{n, plural, one {One} other {Many}}','{n, plural, one {A} one {B} other {C}}'],
    ['I35','duplicate ICU other branch denied','deny','{n, plural, one {One} other {Many}}','{n, plural, one {A} other {B} other {C}}'],
    ['I36','duplicate select ready branch denied','deny','{s, select, ready {Y} other {N}}','{s, select, ready {A} ready {B} other {C}}'],
    ['I37','ICU missing other branch denied','deny','{s, select, ready {Y} other {N}}','{s, select, ready {Y}}'],
    ['I38','ICU missing source category denied','deny','{n, plural, one {One} other {Many}}','{n, plural, other {Many}}'],
    ['I39','ICU formatter type change denied','deny','{x, number}','{x, date}'],
    ['I40','ICU undeclared argument denied','deny','Hello {name}','سلام {name} {secret}'],
];
if (count($checks) !== 40) { throw new RuntimeException('Expected exactly forty distinct checks.'); }

$errors = [];
foreach ($checks as [$id,$label,$expected,$source,$target]) {
    $allowed = true;
    try {
        if ($id[0] === 'M') {
            MarkupValidator::assertEquivalent($source,$target);
        } else {
            MessageFormatValidator::assertEquivalent($source,$target);
        }
    } catch (\InvalidArgumentException $e) {
        $allowed = false;
    }
    $pass = $allowed === ($expected === 'allow');
    echo ($pass ? 'PASS ' : 'FAIL ') . $id . ' ' . $label . "\n";
    if (!$pass) { $errors[] = $id; }
}
echo 'CF-06 FRESH 40 behavioral: ' . (40 - count($errors)) . '/40 passed' . "\n";
exit($errors === [] ? 0 : 1);
