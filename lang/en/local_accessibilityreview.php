<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * English language strings.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['accessibilityreview:fix'] = 'Fix accessibility issues in course content from the report';
$string['accessibilityreview:manage'] = 'Manage accessibility reviews across the site';
$string['accessibilityreview:review'] = 'Review accessibility findings and complete checklists';
$string['accessibilityreview:scan'] = 'Queue accessibility scans';
$string['accessibilityreview:view'] = 'View accessibility reports';
$string['action'] = 'Action';
$string['action_detected'] = 'Detected by scan';
$string['action_fixed'] = 'Fixed from the accessibility report';
$string['action_fixundone'] = 'Fix undone';
$string['action_notdetected'] = 'No longer detected';
$string['action_note'] = 'Note added';
$string['action_reappeared'] = 'Detected again';
$string['action_reopened'] = 'Reopened';
$string['action_review'] = 'Manual review saved';
$string['action_sourceremoved'] = 'Source removed';
$string['action_statechange'] = 'Status changed';
$string['actions'] = 'Actions';
$string['activitylabel'] = '{$a->type}: {$a->name}';
$string['altreason_filename'] = 'The alternative text looks like a file name or camera-generated name.';
$string['altreason_generic'] = 'The alternative text is a generic word that does not describe the image.';
$string['altreason_long'] = 'The alternative text is very long (over 150 characters). Long descriptions are usually better placed in the surrounding text.';
$string['answer_na'] = 'Not applicable';
$string['answer_no'] = 'No';
$string['answer_notchecked'] = 'Not checked';
$string['answer_yes'] = 'Yes';
$string['any'] = 'Any';
$string['applyfilters'] = 'Apply filters';
$string['automatedchecks'] = 'Automated checks';
$string['autoscan'] = 'Scheduled scans';
$string['autoscan_desc'] = 'Automatically queue scans for visible courses whose last completed scan is older than the interval below. Disabled by default; scans can always be queued manually.';
$string['autoscancourses'] = 'Courses per scheduled run';
$string['autoscancourses_desc'] = 'Maximum number of courses queued each time the scheduled scan task runs.';
$string['autoscandays'] = 'Rescan interval (days)';
$string['autoscandays_desc'] = 'Courses are queued again by the scheduled task when their last completed scan is older than this.';
$string['availability'] = 'Availability';
$string['backtofinding'] = 'Back to the finding';
$string['batchsize'] = 'Items per batch';
$string['batchsize_desc'] = 'Number of content items processed between progress saves. Smaller values save progress more often.';
$string['bulkfix'] = 'Fix several at once';
$string['bulkfix_apply'] = 'Apply fixes';
$string['bulkfix_done'] = '{$a->done} fixed, {$a->failed} could not be fixed.';
$string['bulkfix_images_intro'] = 'Describe each image in a few words, or tick "decorative" if it adds no information. Images with nothing entered are left unchanged.';
$string['bulkfix_intro'] = 'Choose a type of issue. You can fix every open finding of that type in this course from one page.';
$string['bulkfix_note'] = 'Each fix changes only the element shown, keeps the previous content so it can be undone from the Fixes tab, and re-checks the item immediately.';
$string['bulkfix_select_intro'] = 'Untick any item you do not want to change, then apply.';
$string['bulkfix_titles_intro'] = 'Enter a title describing each embedded item. Where the video title is known, it is suggested.';
$string['bulkqueued'] = '{$a->queued} scan(s) queued for {$a->total} course(s) (limit {$a->limit} per request). Courses with a scan already queued or running were skipped.';
$string['bulkqueuelimit'] = 'Bulk queue limit';
$string['bulkqueuelimit_desc'] = 'Maximum number of courses queued by one bulk request from the site report.';
$string['captions_auto_none'] = 'Captions: no active caption or subtitle tracks found via the Vimeo API.';
$string['captions_auto_present'] = 'Captions: caption or subtitle track found via the Vimeo API (accuracy not verified).';
$string['captions_auto_unknown'] = 'Captions: unknown (could not be verified automatically).';
$string['category'] = 'Category';
$string['category_contrast'] = 'Colour contrast';
$string['category_frames'] = 'Embedded frames';
$string['category_headings'] = 'Headings';
$string['category_images'] = 'Images';
$string['category_links'] = 'Links';
$string['category_media'] = 'Media and embedded objects';
$string['category_structure'] = 'Structure';
$string['category_tables'] = 'Tables';
$string['category_text'] = 'Text presentation';
$string['change'] = 'Change';
$string['check'] = 'Check';
$string['check_area_alt_missing'] = 'Image map area without alternative text';
$string['check_area_alt_missing_desc'] = 'A clickable area of an image map has no alt text, so screen reader users cannot tell where it leads.';
$string['check_area_alt_missing_fix'] = 'Add alt text to each area describing its destination, or replace the image map with ordinary text links.';
$string['check_area_alt_missing_steps'] = 'Image maps cannot be edited in the TinyMCE toolbar. Open the source code view (the <> button, or Tools > Source code).
Find each <area> element and add alt="…" describing where it leads.
Alternatively, delete the image map and add a list of text links under the image.
Save the page.';
$string['check_area_alt_missing_why'] = 'Screen reader and voice control users.';
$string['check_content_no_headings'] = 'Long content without headings';
$string['check_content_no_headings_desc'] = 'This content is long (over 3,000 characters) but has no headings. Headings let screen reader users jump between sections and help everyone scan the page.';
$string['check_content_no_headings_fix'] = 'Break the content into sections and give each section a heading using the editor\'s heading styles.';
$string['check_content_no_headings_steps'] = 'Open the content in the editor.
Decide on the main sections of the text.
Place the cursor in the first line of each section title and choose a heading level from the Format menu (Format > Blocks > Heading 3, for example) or the paragraph-style drop-down.
Use Heading 3 for main sections and Heading 4 for subsections; Moodle uses Heading 2 for the activity name.
Save.';
$string['check_content_no_headings_why'] = 'Screen reader users, people with cognitive or learning disabilities, and anyone skimming.';
$string['check_contrast_fail'] = 'Insufficient colour contrast';
$string['check_contrast_fail_desc'] = 'Text colour and background colour are both set in the content, and their contrast ratio is below the WCAG AA minimum (4.5:1 for normal text, 3:1 for large text). Only colours set directly in the stored HTML are evaluated.';
$string['check_contrast_fail_fix'] = 'Choose a darker text colour or a lighter background (or the reverse), or remove the inline colours so the theme colours apply.';
$string['check_contrast_fail_steps'] = 'Select the coloured text in the editor.
Use the text colour button to choose a darker colour (or remove the colour so the theme colour applies).
If the text has a background colour, choose a lighter background or remove it.
Check the result with a contrast checker such as the WebAIM Contrast Checker; normal text needs at least 4.5:1.
Save.';
$string['check_contrast_fail_why'] = 'People with low vision or colour vision deficiency, and anyone reading on a phone in bright light.';
$string['check_contrast_review'] = 'Colour contrast needs review';
$string['check_contrast_review_desc'] = 'The contrast ratio is between 3:1 and 4.5:1. This passes only if the text is large (at least 24px, or 18.66px bold). The text size could not be determined from the stored HTML.';
$string['check_contrast_review_fix'] = 'If the text is not large, increase the contrast to at least 4.5:1.';
$string['check_contrast_review_steps'] = 'Check whether the text is large (at least 24px, or about 19px bold).
If it is not large, select the text and choose a colour with more contrast.
Save.';
$string['check_contrast_review_why'] = 'People with low vision or colour vision deficiency.';
$string['check_heading_empty'] = 'Empty heading';
$string['check_heading_empty_desc'] = 'A heading element contains no text. Screen reader users navigating by headings will hear an empty heading.';
$string['check_heading_empty_fix'] = 'Add descriptive heading text, or remove the empty heading element.';
$string['check_heading_empty_steps'] = 'Open the content in the editor.
Place the cursor in the empty heading and either type the heading text or delete the line.
Save.';
$string['check_heading_empty_why'] = 'Screen reader users who navigate by headings.';
$string['check_heading_skip'] = 'Heading level skipped';
$string['check_heading_skip_desc'] = 'Within this content the heading level jumps by more than one (for example from Heading 2 to Heading 4). This is a good-practice issue that can make the structure harder to follow; it is not by itself a WCAG failure. The first heading in a content area is not checked, because the page supplies its own headings.';
$string['check_heading_skip_fix'] = 'Use heading levels in order (for example Heading 3 under Heading 2) to reflect the content structure. Do not choose heading levels for their visual size.';
$string['check_heading_skip_steps'] = 'Open the content in the editor.
Place the cursor in the heading reported.
Choose the heading level one below the previous heading (Format > Blocks > Heading n, or the paragraph-style drop-down).
If you want the text to look smaller or larger, do not change the level for that reason.
Save.';
$string['check_heading_skip_why'] = 'Screen reader users, who use heading levels to understand how sections relate.';
$string['check_heading_visual'] = 'Bold text used as a heading';
$string['check_heading_visual_desc'] = 'A paragraph contains only short bold text, which suggests it is being used as a heading. Visually it looks like a heading, but assistive technology treats it as ordinary text.';
$string['check_heading_visual_fix'] = 'If the text introduces a section, make it a real heading using the editor\'s heading styles. If it is just emphasis, no change is needed.';
$string['check_heading_visual_steps'] = 'Open the content in the editor.
Place the cursor in the bold line.
Choose a heading level from Format > Blocks (Heading 3 for a main section, Heading 4 for a subsection).
Remove the bold formatting; headings are styled by the theme.
Save.';
$string['check_heading_visual_why'] = 'Screen reader users who navigate by headings.';
$string['check_iframe_title_generic'] = 'Embedded frame title may not be descriptive';
$string['check_iframe_title_generic_desc'] = 'The embedded frame has a title, but it is a generic word that may not tell users what the frame contains.';
$string['check_iframe_title_generic_fix'] = 'Use a title that describes the content, for example "Video: Introduction to anatomy".';
$string['check_iframe_title_generic_steps'] = 'Open the source code view (the <> button, or Tools > Source code).
Find the <iframe> and change the title attribute to describe the content, for example title="Video: Pulse diagnosis demonstration".
Save.';
$string['check_iframe_title_generic_why'] = 'Screen reader users.';
$string['check_iframe_title_missing'] = 'Embedded frame without a title';
$string['check_iframe_title_missing_desc'] = 'An iframe (for example an embedded video) has no title attribute or accessible label, so screen reader users cannot tell what it contains.';
$string['check_iframe_title_missing_fix'] = 'Add a title attribute describing the embedded content, for example title="Video: Week 1 introduction".';
$string['check_iframe_title_missing_steps'] = 'Open the source code view (the <> button, or Tools > Source code).
Find the <iframe> and add a title attribute describing the content, for example title="Video: Pulse diagnosis demonstration".
Save.';
$string['check_iframe_title_missing_why'] = 'Screen reader users.';
$string['check_img_alt_empty'] = 'Image marked as decorative';
$string['check_img_alt_empty_desc'] = 'The image has an empty alternative text (alt=""), which tells assistive technology to ignore it. This is correct for purely decorative images, but wrong if the image conveys information.';
$string['check_img_alt_empty_fix'] = 'Confirm the image is decorative. If it conveys information, add alternative text that describes its purpose.';
$string['check_img_alt_empty_steps'] = 'Decide whether the image adds information. If it is purely decorative (a border, a stock photo that repeats the text), leave it as it is.
If it adds information, select the image and click the Image button.
Untick "This image is decorative" and enter a short description of what the image shows or does.
Save.';
$string['check_img_alt_empty_why'] = 'Blind and low-vision users who rely on screen readers.';
$string['check_img_alt_missing'] = 'Image without alternative text';
$string['check_img_alt_missing_desc'] = 'The image has no alt attribute. Screen readers may read the file name instead, or nothing useful.';
$string['check_img_alt_missing_fix'] = 'Add alternative text describing the image\'s purpose. If the image is purely decorative, mark it as decorative (empty alt text).';
$string['check_img_alt_missing_steps'] = 'Open the content in the editor.
Select the image and click the Image button (or double-click the image).
Enter a description in "Describe this image for someone who cannot see it", or tick "This image is decorative" if it adds no information.
Save.';
$string['check_img_alt_missing_why'] = 'Blind and low-vision users who rely on screen readers, and users with images turned off.';
$string['check_img_alt_suspicious'] = 'Alternative text may not be helpful';
$string['check_img_alt_suspicious_desc'] = 'The image has alternative text, but it looks like a file name, a generic word, or is very long.';
$string['check_img_alt_suspicious_fix'] = 'Replace the alternative text with a short description of what the image shows or does in this context.';
$string['check_img_alt_suspicious_steps'] = 'Open the content in the editor.
Select the image and click the Image button.
Replace the description with what the image shows or does in this context, in one or two sentences. Do not start with "Image of".
If the image needs a long explanation (a complex chart, for example), keep the alt text short and explain the details in the text of the page.
Save.';
$string['check_img_alt_suspicious_why'] = 'Blind and low-vision users who rely on screen readers.';
$string['check_legend_empty'] = 'Empty group label (legend)';
$string['check_legend_empty_desc'] = 'A group of form fields (fieldset) has an empty legend, so the group has no label for assistive technology.';
$string['check_legend_empty_fix'] = 'Add text to the legend describing the group, or remove the empty legend.';
$string['check_legend_empty_steps'] = 'Open the source code view (the <> button, or Tools > Source code).
Find the <legend> element and type a label between <legend> and </legend>.
Save.';
$string['check_legend_empty_why'] = 'Screen reader users.';
$string['check_link_empty'] = 'Link without text';
$string['check_link_empty_desc'] = 'The link has no text, no image alternative text and no accessible label, so its purpose cannot be announced.';
$string['check_link_empty_fix'] = 'Add link text describing the destination, or alternative text to the image inside the link.';
$string['check_link_empty_steps'] = 'Open the content in the editor.
If the link contains an image, select the image, click the Image button and describe where the link goes.
If the link is empty, place the cursor on it, click the Link button and enter link text, or remove the link.
Save.';
$string['check_link_empty_why'] = 'Screen reader and voice control users.';
$string['check_link_new_window'] = 'Link opens a new window without warning';
$string['check_link_new_window_desc'] = 'This link opens in a new window or tab, but its text does not say so. Unexpected new windows disorient screen reader users and people with cognitive disabilities, and break the Back button. This is advisory (WCAG AAA).';
$string['check_link_new_window_fix'] = 'Either let the link open in the same window, or add "(opens in new window)" to the link text.';
$string['check_link_new_window_steps'] = 'Open the content in the editor.
Place the cursor on the link and click the Link button.
Either untick "Open in new window", or add "(opens in new window)" to the end of the link text.
Save.';
$string['check_link_new_window_why'] = 'Screen reader users, people with cognitive disabilities, and people using magnification.';
$string['check_link_unclear'] = 'Link text may be unclear';
$string['check_link_unclear_desc'] = 'The link text (for example "click here" or "read more") may not describe its destination. Whether this is acceptable depends on the surrounding context, so it needs human judgement.';
$string['check_link_unclear_fix'] = 'Use link text that makes sense on its own, for example "Download the Week 2 reading list (PDF)".';
$string['check_link_unclear_steps'] = 'Open the content in the editor.
Place the cursor on the link and click the Link button.
Change the link text so it describes the destination, for example "Week 3 reading list (PDF)" instead of "click here".
Save.';
$string['check_link_unclear_why'] = 'Screen reader users, who often list all links on a page out of context.';
$string['check_link_url_text'] = 'Link text is a web address';
$string['check_link_url_text_desc'] = 'The link text is a long URL. Screen readers read it character by character, which is hard to follow. This is advisory.';
$string['check_link_url_text_fix'] = 'Consider replacing the URL with descriptive text, unless the address itself needs to be shown (for example in a printed reference list).';
$string['check_link_url_text_steps'] = 'Open the content in the editor.
Place the cursor on the link and click the Link button.
Replace the address shown as link text with a description, for example "NHS guidance on needle safety".
Save.';
$string['check_link_url_text_why'] = 'Screen reader users, who hear the address read character by character.';
$string['check_list_manual'] = 'List typed by hand';
$string['check_list_manual_desc'] = 'Several consecutive paragraphs start with bullet characters or numbers. They look like a list, but are not marked up as one, so screen readers do not announce the list or the number of items.';
$string['check_list_manual_fix'] = 'Turn the paragraphs into a real bulleted or numbered list with the editor\'s list buttons.';
$string['check_list_manual_steps'] = 'Open the content in the editor.
Select all the paragraphs that form the list.
Click the Bullet list or Numbered list button.
Delete the typed bullet characters or numbers at the start of each item.
Save.';
$string['check_list_manual_why'] = 'Screen reader users.';
$string['check_markup_deprecated'] = 'Obsolete formatting markup';
$string['check_markup_deprecated_desc'] = 'The content uses obsolete HTML formatting elements (such as font or center elements), usually pasted from Word or an older editor. They may override the theme and user display settings. This is a good-practice issue, not a WCAG failure.';
$string['check_markup_deprecated_fix'] = 'Select the affected text and use the editor\'s "Clear formatting" button, then reapply any formatting with the editor\'s own styles.';
$string['check_markup_deprecated_steps'] = 'Open the content in the editor.
Select the affected text (or select all).
Click "Clear formatting" (Format > Clear formatting).
Reapply headings, bold or lists using the editor buttons.
Save.';
$string['check_markup_deprecated_why'] = 'People who change text size, colours or fonts in their browser or in Moodle.';
$string['check_media_autoplay'] = 'Media plays automatically';
$string['check_media_autoplay_desc'] = 'An audio or video element starts playing automatically with sound. This interferes with screen readers and can distress some users.';
$string['check_media_autoplay_fix'] = 'Remove autoplay so the media only plays when the user starts it.';
$string['check_media_autoplay_steps'] = 'Select the media in the editor and click the Multimedia button, then untick "Autoplay".
Or open the source code view and remove the autoplay attribute from the <video> or <audio> element.
Save.';
$string['check_media_autoplay_why'] = 'Screen reader users, and people with attention or anxiety-related conditions.';
$string['check_media_no_captions'] = 'Video without a caption track';
$string['check_media_no_captions_desc'] = 'An embedded video has no captions or subtitles track. Captions may still be burned into the video image, so this needs checking.';
$string['check_media_no_captions_fix'] = 'Add a captions file (WebVTT) to the video, or confirm the captions are part of the video image.';
$string['check_media_no_captions_steps'] = 'Create a captions file in WebVTT (.vtt) format, for example by exporting from your video platform or editing automatic captions.
Select the video in the editor and click the Multimedia button.
Open the "Subtitles and captions" section and upload the .vtt file as a caption track.
Save.';
$string['check_media_no_captions_why'] = 'Deaf and hard-of-hearing users, non-native speakers, and anyone watching without sound.';
$string['check_motion_blink'] = 'Blinking or scrolling text';
$string['check_motion_blink_desc'] = 'The content uses blinking or automatically scrolling text (blink, marquee), which users cannot pause. It is distracting and can be hard or impossible to read.';
$string['check_motion_blink_fix'] = 'Replace the moving text with static text.';
$string['check_motion_blink_steps'] = 'Open the source code view (the <> button, or Tools > Source code).
Remove the <marquee> or <blink> tags (keep the text between them), or remove "text-decoration: blink" from the style.
Save.';
$string['check_motion_blink_why'] = 'People with attention or reading difficulties, low vision, or vestibular disorders.';
$string['check_object_title_missing'] = 'Embedded object without a title';
$string['check_object_title_missing_desc'] = 'An embedded object or plug-in (object or embed element) has no title or accessible label, so screen reader users cannot tell what it contains.';
$string['check_object_title_missing_fix'] = 'Add a title attribute describing the embedded content, or replace the embed with a link to the content.';
$string['check_object_title_missing_steps'] = 'Open the source code view (the <> button, or Tools > Source code).
Find the <object> or <embed> element and add title="…" describing what it contains.
If it embeds a document, consider replacing it with a normal link to the file.
Save.';
$string['check_object_title_missing_why'] = 'Screen reader users.';
$string['check_table_complex'] = 'Complex table';
$string['check_table_complex_desc'] = 'This data table has merged cells (colspan or rowspan) but its cells are not explicitly linked to their headers, so screen readers may announce the wrong header for some cells.';
$string['check_table_complex_fix'] = 'Simplify the table so each cell has one row header and one column header, or split it into several simple tables.';
$string['check_table_complex_steps'] = 'Open the content in the editor.
Place the cursor in the merged cell and use Table > Cell > Split cell, or rebuild the table without merged cells.
If one table holds several groups of data, consider separate tables, each with its own header row.
Make sure the first row is a header row (Table > Row > Row properties > Row type: Header).
Save.';
$string['check_table_complex_why'] = 'Screen reader users.';
$string['check_table_no_headers'] = 'Table without identifiable headers';
$string['check_table_no_headers_desc'] = 'The table has several rows and columns but no header cells (th) or header roles. If it is a data table, screen reader users cannot relate cells to their headers. If it is used only for layout, headers are not required.';
$string['check_table_no_headers_fix'] = 'For data tables, mark the header row or column as headers in the editor\'s table settings. For layout tables, consider replacing the table with other layout, or mark it role="presentation".';
$string['check_table_no_headers_steps'] = 'Open the content in the editor.
Place the cursor in the first row of the table.
Choose Table > Row > Row properties and set "Row type" to "Header".
If the first column holds headers too, select it and use Table > Cell > Cell properties > Cell type: Header cell.
If the table is only for layout, consider replacing it with headings and paragraphs.
Save.';
$string['check_table_no_headers_why'] = 'Screen reader users, who hear each cell together with its headers.';
$string['check_table_summary_duplicate'] = 'Table summary repeats the caption';
$string['check_table_summary_duplicate_desc'] = 'The table\'s summary attribute repeats its caption, so screen reader users hear the same text twice. The summary attribute is obsolete.';
$string['check_table_summary_duplicate_fix'] = 'Remove the summary attribute and keep the caption.';
$string['check_table_summary_duplicate_steps'] = 'Open the source code view (the <> button, or Tools > Source code).
Find the <table> element and delete the summary="…" attribute.
Save.';
$string['check_table_summary_duplicate_why'] = 'Screen reader users.';
$string['check_text_all_caps'] = 'Long text in capital letters';
$string['check_text_all_caps_desc'] = 'A long passage is written in capital letters. It is harder to read for many people, and some screen readers spell out words in capitals letter by letter.';
$string['check_text_all_caps_fix'] = 'Rewrite the text in sentence case. Use bold or a heading for emphasis.';
$string['check_text_all_caps_steps'] = 'Open the content in the editor.
Retype the text in sentence case (or select it and use a case-changing tool, then correct proper nouns).
If the capitals were for emphasis, use bold or a heading instead.
Save.';
$string['check_text_all_caps_why'] = 'People with dyslexia or low vision, and some screen reader users.';
$string['check_text_underline'] = 'Underlined text that is not a link';
$string['check_text_underline_desc'] = 'Underlined text looks like a link on the web, which confuses users who try to click it. This is advisory.';
$string['check_text_underline_fix'] = 'Use bold or a heading for emphasis instead of underlining.';
$string['check_text_underline_steps'] = 'Open the content in the editor.
Select the underlined text and click the Underline button to remove it.
Use bold for emphasis, or a heading if the text introduces a section.
Save.';
$string['check_text_underline_why'] = 'People with cognitive disabilities, low vision, or limited web experience.';
$string['checkedon'] = 'checked {$a}';
$string['checkid'] = 'Check identifier';
$string['checklist'] = 'Manual review checklist';
$string['checklist_intro'] = 'Record what a person has checked. Answer "Yes" when the item meets the requirement.';
$string['checklist_pdf_headings'] = 'Headings are meaningful and tagged';
$string['checklist_pdf_headings_help'] = 'Headings in the document are tagged as headings, in a logical order, and describe their sections.';
$string['checklist_pdf_images'] = 'Images have descriptions';
$string['checklist_pdf_images_help'] = 'Informative images have alternative text; decorative images are marked as artifacts.';
$string['checklist_pdf_language'] = 'Document language is set';
$string['checklist_pdf_language_help'] = 'The document language is set in the document properties so screen readers use the correct pronunciation.';
$string['checklist_pdf_readingorder'] = 'Reading order is logical';
$string['checklist_pdf_readingorder_help'] = 'Using a screen reader, the reflow view or the tags/order panel, content is read in a logical order.';
$string['checklist_pdf_realtext'] = 'Text is real, selectable text';
$string['checklist_pdf_realtext_help'] = 'The document contains real text (not only scanned images of text). Scanned documents need OCR and checking.';
$string['checklist_pdf_tables'] = 'Data tables have headers';
$string['checklist_pdf_tables_help'] = 'Data tables are tagged with header cells. Answer "Not applicable" if there are no data tables.';
$string['checklist_pdf_tagged'] = 'Document is tagged and tags are correct';
$string['checklist_pdf_tagged_help'] = 'The PDF is tagged and the tags have been checked in a PDF accessibility tool; tags exist and are correct.';
$string['checklist_pdf_usable'] = 'Usable with keyboard and screen reader';
$string['checklist_pdf_usable_help'] = 'Links, form fields and bookmarks work with the keyboard and are announced correctly by a screen reader.';
$string['checklist_scorm_captions'] = 'Captions or transcripts for media';
$string['checklist_scorm_captions_help'] = 'Audio and video inside the package have accurate captions or transcripts. Answer "Not applicable" if there is no media.';
$string['checklist_scorm_contrast'] = 'Sufficient colour contrast';
$string['checklist_scorm_contrast_help'] = 'Text and essential graphics have sufficient contrast, and information is not conveyed by colour alone.';
$string['checklist_scorm_focus'] = 'Visible focus';
$string['checklist_scorm_focus_help'] = 'The keyboard focus is always clearly visible.';
$string['checklist_scorm_keyboard'] = 'Keyboard navigation';
$string['checklist_scorm_keyboard_help'] = 'All content, navigation and interactions (including quizzes and drag and drop) can be completed with the keyboard alone, without traps.';
$string['checklist_scorm_screenreader'] = 'Screen-reader usability';
$string['checklist_scorm_screenreader_help'] = 'The package was tested with a screen reader: content, controls and feedback are announced and usable.';
$string['checklist_scorm_textalt'] = 'Text alternatives';
$string['checklist_scorm_textalt_help'] = 'Informative images and controls have text alternatives.';
$string['checklist_video_audiodesc'] = 'Audio description reviewed';
$string['checklist_video_audiodesc_help'] = 'Important visual information not described in the audio is available through audio description or an equivalent. Answer "Not applicable" if the visuals carry no extra information.';
$string['checklist_video_captions'] = 'Captions present';
$string['checklist_video_captions_help'] = 'Synchronised captions are available for the video.';
$string['checklist_video_captionsaccurate'] = 'Captions checked for accuracy';
$string['checklist_video_captionsaccurate_help'] = 'A person has checked that captions are accurate, synchronised and identify speakers and relevant sounds. Automatic captions usually need correction.';
$string['checklist_video_transcript'] = 'Transcript available';
$string['checklist_video_transcript_help'] = 'A text transcript is available alongside the video.';
$string['checklistincomplete'] = 'Answer every checklist question before marking the item as reviewed with no issues.';
$string['checksperformed'] = 'Checks performed: {$a}';
$string['contenttype'] = 'Content type';
$string['contrastdetails'] = 'Measured contrast ratio {$a->ratio}:1 between {$a->fg} (text) and {$a->bg} (background). Required: {$a->required}:1.';
$string['count'] = 'Count';
$string['countof'] = '{$a->done} of {$a->total}';
$string['coursereport'] = 'Accessibility review';
$string['currentscan'] = 'Current scan';
$string['date'] = 'Date';
$string['deleteduser'] = 'Deleted user';
$string['details'] = 'Details';
$string['detailsfor'] = 'Details: {$a}';
$string['disclaimer'] = 'Automated checks find only some accessibility problems in stored content. They do not test the theme, rendered pages, PDFs, videos or SCORM packages in full, and a result with no issues is not WCAG certification or proof that a course is accessible. Manual review is still required.';
$string['editsource'] = 'Edit';
$string['enablefixes'] = 'Allow fixing content from the report';
$string['enablefixes_desc'] = 'Lets users with the "Fix content" capability (and the normal editing capability for that content) correct alternative text, frame titles, table headers, heading levels, new-window links and autoplay directly from the report. Every change can be undone while the content has not been edited since. Quiz questions are never changed.';
$string['error_coursemissing'] = 'The course no longer exists.';
$string['error_item'] = 'Could not scan "{$a->item}": {$a->message}';
$string['error_scanexception'] = 'The scan stopped because of an error: {$a}';
$string['error_stalled'] = 'The scan made no progress for more than a day and was stopped. Check that cron is running, then queue a new scan.';
$string['error_toolarge'] = 'Skipped "{$a}": the content is too large to check automatically.';
$string['event_contentfixed'] = 'Accessibility fix applied or undone';
$string['evidence_exceptions'] = 'Accepted exceptions';
$string['evidence_findings'] = 'Findings by check';
$string['evidence_fixes'] = '{$a} fixes have been applied to this course from the accessibility report. Details are in the Fixes tab.';
$string['evidence_footer'] = 'This report records automated checks and human review decisions for this course. Automated checks cover only part of WCAG; the results do not show that the course conforms to WCAG, EN 301 549 or any law.';
$string['evidence_generated'] = 'Generated';
$string['evidence_noexceptions'] = 'No findings have been accepted as exceptions.';
$string['evidence_noscans'] = 'No completed scans yet.';
$string['evidence_notconformance'] = 'This is a record of the review process, not a statement of conformance. Automated checks find only some problems, and only in stored content.';
$string['evidence_scans'] = 'Recent scans';
$string['evidence_scope'] = 'Scope';
$string['evidence_scope_areas'] = 'Content areas checked:';
$string['evidence_scope_checks'] = '{$a} automated checks were run on the stored HTML of these areas. PDFs, videos and SCORM packages are listed for manual review.';
$string['evidence_scope_limits'] = 'Not tested: the theme and rendered pages, content added by filters or plugins at display time, the inside of files (except optional basic PDF signals), keyboard operation, screen reader behaviour and the accuracy of captions and alternative text.';
$string['evidence_tool'] = 'Tool';
$string['evidencereport'] = 'Evidence report';
$string['exceptionneedsnote'] = 'Explain why this is an accepted exception or not applicable.';
$string['excerpt'] = 'Excerpt';
$string['explanation'] = 'Explanation';
$string['exportcsv'] = 'Export CSV';
$string['field'] = 'Field';
$string['field_content'] = 'Content';
$string['field_externalurl'] = 'External URL';
$string['field_intro'] = 'Description';
$string['field_summary'] = 'Summary';
$string['filters'] = 'Filters';
$string['findingdetails'] = 'Finding details';
$string['findinglink'] = 'Link';
$string['findings'] = 'Findings';
$string['findingscount'] = '{$a} finding(s) match the filters.';
$string['findingtype'] = 'Type';
$string['firstseen'] = 'First detected';
$string['fix_after'] = 'After';
$string['fix_alttext'] = 'Alternative text';
$string['fix_alttext_help'] = 'Describe what the image shows or does in this context, briefly (usually one sentence). Do not start with "Image of". If the image needs a long explanation, describe it in the text of the page instead.';
$string['fix_alttextfor'] = 'Alternative text for the image in {$a}';
$string['fix_apply'] = 'Apply fix';
$string['fix_autoplay_info'] = 'The autoplay attribute will be removed, so the media plays only when started.';
$string['fix_before'] = 'Before';
$string['fix_decorative'] = 'Decorative image';
$string['fix_decorative_help'] = 'Tick this only if the image adds no information (for example a border or a photo that repeats the text). Screen readers will then skip it.';
$string['fix_decorative_label'] = 'This image is decorative';
$string['fix_error_alreadyundone'] = 'This fix has already been undone.';
$string['fix_error_changed'] = 'The content has changed since it was last scanned, and the issue was not found in its current form. Rescan the course and try again.';
$string['fix_error_disabled'] = 'Fixing content from the report is turned off on this site.';
$string['fix_error_format'] = 'This content is not stored as HTML (for example plain text or Markdown), so it cannot be fixed here. Use the Edit link instead.';
$string['fix_error_invalidlevel'] = 'Choose a heading level from 1 to 6.';
$string['fix_error_mismatch'] = 'The element could not be matched safely in the stored content. Nothing was changed. Use the Edit link instead.';
$string['fix_error_nochange'] = 'This fix would not change anything.';
$string['fix_error_nopermission'] = 'You do not have permission to edit this content.';
$string['fix_error_notfixable'] = 'This type of issue cannot be fixed automatically. Use the Edit link and the guide.';
$string['fix_error_notfound'] = 'The element could not be found in the stored content. Nothing was changed.';
$string['fix_error_notopen'] = 'Only open or in-progress findings that are still detected can be fixed.';
$string['fix_error_notsupported'] = 'Content in this area cannot be fixed here (for example quiz questions, which are versioned). Use the Edit link instead.';
$string['fix_error_tablecomplex'] = 'This table contains another table, so headers cannot be added automatically. Use the Edit link instead.';
$string['fix_error_textrequired'] = 'Enter some text.';
$string['fix_error_texttoolong'] = 'Use 250 characters or fewer. Put longer explanations in the text of the page.';
$string['fix_error_undochanged'] = 'The content has been edited since this fix was applied, so it cannot be undone automatically. Edit the content directly instead.';
$string['fix_headers'] = 'Header cells';
$string['fix_headers_both'] = 'First row and first column';
$string['fix_headers_column'] = 'First column (row headers)';
$string['fix_headers_row'] = 'First row (column headers)';
$string['fix_image'] = 'Image';
$string['fix_intro'] = 'Only the element shown is changed. The previous content is kept so the fix can be undone from the Fixes tab, and the item is re-checked as soon as the fix is saved. Use Preview to see the change first.';
$string['fix_label'] = 'Text to add to the link';
$string['fix_label_default'] = '(opens in new window)';
$string['fix_level'] = 'New heading level';
$string['fix_level_option'] = 'Heading {$a}';
$string['fix_mode'] = 'How to fix';
$string['fix_mode_label'] = 'Keep opening in a new window, and say so in the link text';
$string['fix_mode_sametab'] = 'Open the link in the same window';
$string['fix_preview'] = 'Preview';
$string['fix_previewheading'] = 'Preview of the change';
$string['fix_previewnote'] = 'Nothing has been saved yet. Apply the fix to save it.';
$string['fix_title'] = 'Title';
$string['fix_title_help'] = 'Describe the embedded content, for example "Video: Pulse diagnosis demonstration". Screen readers announce this title when users reach the frame.';
$string['fix_titlefor'] = 'Title for the embedded item in {$a}';
$string['fixapplied_resolved'] = 'Fix applied. The item was re-checked and the issue is no longer detected.';
$string['fixapplied_stillpresent'] = 'Fix applied, but the item was re-checked and the issue is still detected. Check the new text.';
$string['fixdesc_decorative'] = 'Marked the image as decorative.';
$string['fixdesc_headers_both'] = 'Made the first row and first column header cells.';
$string['fixdesc_headers_column'] = 'Made the first column header cells.';
$string['fixdesc_headers_row'] = 'Made the first row header cells.';
$string['fixdesc_label'] = 'Added a new-window warning to the link text.';
$string['fixdesc_level'] = 'Changed the heading to level {$a}.';
$string['fixdesc_media_autoplay'] = 'Removed autoplay.';
$string['fixdesc_sametab'] = 'Made the link open in the same window.';
$string['fixdesc_text'] = 'Set the text to: {$a}';
$string['fixes_intro'] = 'Changes made to course content from this report. A fix can be undone while the content has not been edited since.';
$string['fixstatus_applied'] = 'Applied';
$string['fixstatus_undone'] = 'Undone by {$a->user}, {$a->time}';
$string['fixthis'] = 'Fix this';
$string['fixthisfor'] = 'Fix this: {$a}';
$string['fixtitle'] = 'Fix';
$string['fixundone'] = 'The fix was undone and the item re-checked.';
$string['genericalttext'] = 'Generic alternative text';
$string['genericalttext_desc'] = 'Alternative text values (one per line) that are flagged as not descriptive.';
$string['guide'] = 'Guide';
$string['guide_all'] = 'All guides';
$string['guide_fix'] = 'How to fix it';
$string['guide_fixable'] = 'This can also be fixed directly from the accessibility report with "Fix this", if your site allows it.';
$string['guide_intro'] = 'Practical guidance for staff on each issue the accessibility report can find, with steps for the Moodle editor (TinyMCE).';
$string['guide_steps'] = 'Steps in the Moodle editor';
$string['guide_what'] = 'What the problem is';
$string['guide_who'] = 'Who it affects';
$string['guides'] = 'Accessibility guides';
$string['headingdetails'] = 'Heading level jumps from {$a->from} to {$a->to}.';
$string['history'] = 'History';
$string['history_filechanged'] = 'The file changed since it was reviewed.';
$string['history_notdetected_note'] = 'The automated check no longer detects this issue.';
$string['history_reappeared_note'] = 'The issue was detected again after it had been resolved.';
$string['history_reopened_note'] = 'The source content changed since this finding was reviewed.';
$string['history_sourceremoved_note'] = 'The content containing this issue no longer exists.';
$string['howtofix'] = 'How to fix';
$string['howtofixfor'] = 'How to fix: {$a}';
$string['httpcode'] = 'HTTP code';
$string['include'] = 'Include';
$string['includeabsent'] = 'Include no longer detected';
$string['includeitem'] = 'Include {$a}';
$string['invalidstate'] = 'Invalid status.';
$string['inventory'] = 'PDF, video and SCORM inventory';
$string['inventory_pdf_intro'] = 'PDF files in course summaries, sections, activity descriptions, File, Folder, Page and Book resources, plus links to external PDFs. Each PDF needs a manual review.';
$string['inventory_scorm_intro'] = 'SCORM packages in this course. Packages are not opened or run during scanning; use the checklist to record a manual review.';
$string['inventory_unreviewed'] = 'Files and media not reviewed';
$string['inventory_video_intro'] = 'Vimeo and YouTube videos referenced in course content, and uploaded video files. Caption status is only marked as verified when there is reliable evidence.';
$string['inventoryopen'] = 'Inventory items needing review';
$string['inventoryreviewed'] = 'Inventory items reviewed';
$string['itemsfailed'] = 'Items that could not be scanned';
$string['itemsscanned'] = 'Items scanned';
$string['lastchecked'] = 'Last checked';
$string['lastcompletedscan'] = 'Last completed scan';
$string['lastreviewed'] = 'Last reviewed';
$string['lastseen'] = 'Last detected';
$string['license_trial_expired'] = 'The trial has expired. Activate a licence to continue.';
$string['license_unlicensed'] = 'A valid licence is required to use this plugin.';
$string['license_valid'] = 'The licence is active.';
$string['licenseactivate'] = 'Activate licence';
$string['licenseactivated'] = 'Licence activated.';
$string['licensebuy'] = 'Buy a licence';
$string['licensefailed'] = 'Activation failed. Check the key and try again.';
$string['licenseheading'] = 'Licence';
$string['licensekey'] = 'Licence key';
$string['licenseplan'] = 'Plan: {$a}';
$string['licensesettings'] = 'Manage licence';
$string['licensesupport'] = 'Updates and support until {$a}';
$string['licensetrial'] = 'Request a 14-day trial';
$string['limitation_pdf'] = 'Text extraction, tags or language metadata do not establish that a PDF is accessible. Reading order, meaningful headings, image descriptions and usability need to be assessed by a person.';
$string['limitation_scorm'] = 'SCORM packages are not analysed automatically. Parsing a package manifest would not establish accessibility; test the package with a keyboard and a screen reader.';
$string['limitation_video'] = 'Captions are only marked as present when the Vimeo API confirms it. Caption accuracy, transcripts and audio description always need human review. "Unknown" does not mean captions are missing.';
$string['linkcheck'] = 'Enable link checking';
$string['linkcheck_desc'] = 'Check the availability of external links and videos in the background. The server makes outbound HTTP requests; internal, private and cloud metadata addresses are always blocked. No Moodle cookies or credentials are sent.';
$string['linkcheckdisabled'] = 'Link checking is disabled by the site administrator, so link status is not updated.';
$string['linkconfirmattempts'] = 'Checks before confirming unavailable';
$string['linkconfirmattempts_desc'] = 'A link is only reported as confirmed unavailable after this many consecutive "not found" results, to avoid false alarms from temporary errors.';
$string['linkhostdelay'] = 'Delay between requests to the same host (ms)';
$string['linkhostdelay_desc'] = 'Minimum pause between requests to the same host, to avoid overloading remote servers.';
$string['linkrecheckdays'] = 'Recheck interval (days)';
$string['linkrecheckdays_desc'] = 'Checked links are cached and rechecked after this many days. Links with unknown status are retried after one day.';
$string['links_separate'] = 'Link availability is reported separately from accessibility: a working link is not necessarily accessible, and a restricted link is not necessarily broken.';
$string['linksperrun'] = 'Links per task run';
$string['linksperrun_desc'] = 'Maximum number of links checked each time the link-check task runs.';
$string['linkstatus'] = 'Link status';
$string['linkstatus_available'] = 'Available';
$string['linkstatus_ratelimited'] = 'Rate limited';
$string['linkstatus_restricted'] = 'Authentication required / restricted';
$string['linkstatus_unavailable'] = 'Confirmed unavailable';
$string['linkstatus_unchecked'] = 'Not checked yet';
$string['linkstatus_unknown'] = 'Unknown / unable to verify';
$string['linksummary'] = 'Link availability summary';
$string['linktimelimit'] = 'Link task time limit (seconds)';
$string['linktimelimit_desc'] = 'Maximum time one run of the link-check task may spend.';
$string['linktimeout'] = 'Request timeout (seconds)';
$string['linktimeout_desc'] = 'Timeout for each outbound request.';
$string['location'] = 'Location';
$string['manual'] = 'How to use the accessibility review';
$string['manual_admin'] = 'For site administrators';
$string['manual_admin_text'] = 'Settings are at Site administration > Plugins > Local plugins > Accessibility review. Link checking, PDF analysis and fixing from the report are off until you turn them on.
* The site report (Site administration > Reports > Accessibility review) lists every course with its status, and can queue scans for a whole category.
* Scans, link checks and PDF analysis run through cron, so cron must be running.
* Who can view, scan, review, fix and manage is controlled by the capabilities under local/accessibilityreview in Define roles.';
$string['manual_files'] = 'PDFs, videos and SCORM packages';
$string['manual_files_text'] = 'The scanner cannot check inside these files reliably, so it lists them for a person to review.
* PDFs: if your site has PDF analysis turned on, each PDF shows basic signals such as whether it has real text and tags. "Waiting for analysis" means the hourly task has not reached the file yet; check again later.
* Videos: the Videos tab lists uploaded, Vimeo and YouTube videos, with whether they can be reached and why not.
* SCORM: packages are listed for review because their content cannot be scanned.
Select Review on an item and answer the checklist, for example whether a video has accurate captions. Add notes, choose a review status and select Save review. "Reviewed: no issues found" needs every question answered. If a file is replaced, its review is reopened.';
$string['manual_findings'] = 'Reading the findings';
$string['manual_findings_text'] = 'The Findings tab lists every issue found. Use the filters to narrow the list by category, check, severity, content type, type or status, then select Apply filters.
* Errors are clear barriers, such as an image with no alternative text.
* Warnings are likely problems that need a look.
* Notices are suggestions, or things that may be fine in context.
The type tells you how sure the scanner is. Automated findings are detected reliably. Needs review findings need a person to decide, for example whether link text makes sense in context.
Each row has links to Details, How to fix, and View or Edit the content where you have permission. Export CSV downloads the filtered list as a spreadsheet.';
$string['manual_fixing'] = 'Fixing issues from the report';
$string['manual_fixing_text'] = 'If your administrator has allowed it, editing teachers can fix some issues without opening the Moodle editor: missing or unhelpful image alternative text, decorative images, frame and video titles, table headers, heading levels, links that open a new window, and media that plays automatically.
* Select Fix this on a finding, enter the correction and select Preview to see exactly what will change.
* Select Apply fix to save it. The content is checked again straight away.
* Fix several at once lists every issue of one kind in the course, with one field each, so you can work through them quickly.
Every fix is listed on the Fixes tab. Select Undo there to restore the previous content, as long as the content has not been edited since.
Any other issue is fixed in Moodle as usual: select Edit, follow the steps in How to fix, and save.';
$string['manual_guides'] = 'Guides for staff and students';
$string['manual_guides_text'] = 'Accessibility guides explain every issue the report can find, who it affects and how to fix it step by step in the Moodle editor. Open a guide from How to fix on any finding, or from the Guides button on the Overview tab.
The student guide, Making course content work for you, explains reader mode, zoom, read-aloud, contrast settings and captions. You can link to it from your course.';
$string['manual_intro'] = 'This manual explains how to check a course with the accessibility review, work through what it finds and record your decisions. It is written for teachers, course designers and anyone reviewing courses.';
$string['manual_limits'] = 'What the review cannot tell you';
$string['manual_limits_text'] = 'The report helps you find and track problems. It does not certify that a course is accessible or meets WCAG.
Automated checks find only some kinds of problem, and only in content stored in Moodle. They do not test the theme, keyboard use, the inside of files and videos, or whether text is clear and easy to read. A person still needs to review the course, ideally with a keyboard and a screen reader.';
$string['manual_links'] = 'Link availability';
$string['manual_links_text'] = 'If your site checks links, the Link availability tab shows whether each web link in the course works.
* Available: the link worked.
* Confirmed unavailable: the link failed repeatedly and is very likely broken. Fix or remove it.
* Authentication required / restricted: the page needs a login, or a video only plays on approved websites. This is usually fine.
* Unknown / unable to verify: the check could not decide. Open the link yourself.
* Not checked yet: links are checked in the background, a few at a time.';
$string['manual_overview'] = 'The Overview tab';
$string['manual_overview_text'] = 'The Overview tab shows the scan status of the course, the number of open findings and how they have changed over time.
* Not scanned: the course has not been checked yet.
* Scan in progress: a scan is queued or running.
* Scan incomplete: the scan stopped, or some items could not be checked. The messages say why.
* Issues to review: there are open findings.
* No automated issues detected: the automated checks found nothing open. Manual review is still needed.
* Manually reviewed: nothing is open, and every PDF, video and SCORM package has a completed review.
The buttons below the status open the evidence report, the accessibility guides and, when fixing is allowed, the page for fixing several issues at once.';
$string['manual_progress'] = 'Progress and the evidence report';
$string['manual_progress_text'] = 'Progress over time, on the Overview tab, shows the number of open errors, warnings and notices after each scan, so you can see the course improving. There is no percentage score, because automated checks cannot measure full accessibility.
The Evidence report is a printable summary for quality reviews and accessibility statements: what was checked, recent scans, findings by check with WCAG 2.2 and EN 301 549 references, accepted exceptions with their reasons, fixes and manual reviews. Use your browser\'s Print option to save it as a PDF.';
$string['manual_scans'] = 'Scanning a course';
$string['manual_scans_text'] = 'Select Queue scan on the Overview tab. The scan runs in the background, usually within a few minutes, and never changes course content. Refresh the page to see the results.
Courses are also scanned automatically:
* a few minutes after content is added or edited, if your site has this turned on;
* on the schedule your administrator has set.
The scan checks course and section summaries, activity descriptions, Text and media areas, Pages, Book chapters, Glossary entries, Lesson pages and quiz question text.';
$string['manual_start'] = 'Opening the report';
$string['manual_start_text'] = 'In your course, open More > Accessibility review. In some themes it is in the course administration (cog) menu instead.
Site administrators can see every course at Site administration > Reports > Accessibility review.
You need permission to view the report. Teachers and managers have it by default.';
$string['manual_workflow'] = 'Working through findings';
$string['manual_workflow_text'] = 'Open Details on a finding to see where it is, why it matters, the WCAG reference and its history. Set a status and add a note:
* Open: not looked at yet.
* In progress: someone is working on it.
* Resolved: it has been fixed.
* Accepted exception / not applicable: it is not a problem here. A note explaining why is required, for example "The image is decorative".
When you fix the content in Moodle, the next scan marks the finding "No longer detected" and resolves it for you.
If the content changes later, resolved findings and exceptions are reopened automatically so they can be checked again.';
$string['maxredirects'] = 'Maximum redirects';
$string['maxredirects_desc'] = 'Maximum redirects followed for one link. Every redirect target is validated again.';
$string['maxruntime'] = 'Scan time per task run (seconds)';
$string['maxruntime_desc'] = 'A scan task stops after this time, saves its position and continues in a new task.';
$string['name'] = 'Name';
$string['never'] = 'Never';
$string['nofindings'] = 'No findings match the filters.';
$string['nofixes'] = 'No fixes have been applied in this course.';
$string['nohistory'] = 'No history yet.';
$string['nolinks'] = 'No external links recorded. Run a scan to collect links.';
$string['none'] = 'None';
$string['noresources_pdf'] = 'No PDFs recorded. Run a scan to build the inventory.';
$string['noresources_scorm'] = 'No SCORM packages recorded. Run a scan to build the inventory.';
$string['noresources_video'] = 'No videos recorded. Run a scan to build the inventory.';
$string['notdetected'] = 'No longer detected';
$string['notes'] = 'Notes';
$string['notetoolong'] = 'Notes must be 5000 characters or fewer.';
$string['open'] = 'Open';
$string['openfile'] = 'Download file';
$string['openfindings'] = 'Open and in-progress findings';
$string['opensnewwindow'] = 'Opens in a new window';
$string['opensnewwindow_short'] = '(opens in new window)';
$string['pathpdfinfo'] = 'Path to pdfinfo';
$string['pathpdfinfo_desc'] = 'Full path to the Poppler pdfinfo executable. If empty, or if executable paths are locked by $CFG->preventexecpath, the standard locations /usr/bin, /usr/local/bin and /bin are used automatically.';
$string['pathpdftotext'] = 'Path to pdftotext';
$string['pathpdftotext_desc'] = 'Full path to the Poppler pdftotext executable. If empty, or if executable paths are locked by $CFG->preventexecpath, the standard locations /usr/bin, /usr/local/bin and /bin are used automatically.';
$string['pdf_analysisoff'] = 'Not analysed: PDF analysis is turned off in the plugin settings.';
$string['pdf_encrypted_no'] = 'Not encrypted.';
$string['pdf_encrypted_yes'] = 'Encrypted (may restrict assistive technology).';
$string['pdf_error_encrypted'] = 'The PDF is encrypted or password protected and could not be fully analysed.';
$string['pdf_error_filemissing'] = 'The file could not be found.';
$string['pdf_error_internal'] = 'An unexpected error stopped the analysis of this file. The error is in the output of the "Analyse PDFs" task.';
$string['pdf_error_parseerror'] = 'The PDF could not be parsed.';
$string['pdf_error_tempcopy'] = 'The file could not be copied for analysis.';
$string['pdf_error_timeout'] = 'Analysis timed out.';
$string['pdf_error_toolarge'] = 'Skipped: the file is larger than the configured limit.';
$string['pdf_external'] = 'External PDF: not downloaded or analysed.';
$string['pdf_language_notdetermined'] = 'Document language: not determined.';
$string['pdf_language_present'] = 'Document language entry found.';
$string['pdf_notools'] = 'Not analysed: pdfinfo and pdftotext were not found on the server.';
$string['pdf_pending'] = 'Waiting for analysis. The "Analyse PDFs" task last ran {$a->last} and runs next {$a->next}.';
$string['pdf_pending_never'] = 'Waiting for analysis. The "Analyse PDFs" task has not run yet. It runs every hour at 45 minutes past, if cron is running.';
$string['pdf_pending_taskdisabled'] = 'Waiting for analysis, but the "Analyse PDFs" scheduled task is disabled. An administrator can enable it in Site administration > Server > Scheduled tasks.';
$string['pdf_pending_taskfailing'] = 'Waiting for analysis. The last run of the "Analyse PDFs" task failed; Moodle will retry it. An administrator can see the error in the task logs.';
$string['pdf_tagged_no'] = 'No structure tags detected.';
$string['pdf_tagged_yes'] = 'Structure tags appear present (correctness not verified).';
$string['pdf_text_minimal'] = 'Very little extractable text in the first {$a} page(s).';
$string['pdf_text_none'] = 'No extractable text in the first {$a} page(s) (possibly a scanned document).';
$string['pdf_text_present'] = 'Extractable text found in the first {$a} page(s).';
$string['pdfanalysis'] = 'Enable PDF analysis';
$string['pdfanalysis_desc'] = 'Analyse PDFs on this server with Poppler pdfinfo/pdftotext. Requires the executables below and PHP proc_open. Files are copied to a temporary file through the Moodle File API, analysed locally and deleted. Documents are never sent outside the Moodle environment. The plugin works without this.';
$string['pdfanalysisdisabled'] = 'Automated PDF analysis is not enabled, so only the inventory and manual checklist are available.';
$string['pdfmaxpages'] = 'Pages checked for text';
$string['pdfmaxpages_desc'] = 'Number of pages from the start of each PDF checked for extractable text.';
$string['pdfmaxsize'] = 'Maximum PDF size (MB)';
$string['pdfmaxsize_desc'] = 'Larger PDFs are not analysed.';
$string['pdfperrun'] = 'PDFs per task run';
$string['pdfperrun_desc'] = 'Maximum number of PDFs analysed each time the PDF task runs.';
$string['pdftasktimelimit'] = 'PDF task time limit (seconds)';
$string['pdftasktimelimit_desc'] = 'Maximum time one run of the PDF task may spend.';
$string['pdftimeout'] = 'Analysis timeout per tool (seconds)';
$string['pdftimeout_desc'] = 'Each pdfinfo/pdftotext process is terminated after this time.';
$string['pdftoolnotfound'] = 'not found';
$string['pdftoolsdetected'] = 'PDF tools in use';
$string['performed_langscan'] = 'language metadata scan';
$string['performed_pdfinfo'] = 'pdfinfo';
$string['performed_pdftotext'] = 'text extraction';
$string['pluginname'] = 'Accessibility review';
$string['present'] = 'Currently detected';
$string['privacy:metadata:finding'] = 'Accessibility findings, including who last reviewed them.';
$string['privacy:metadata:finding:courseid'] = 'The course the finding belongs to.';
$string['privacy:metadata:finding:reviewerid'] = 'The user who last changed the finding\'s status.';
$string['privacy:metadata:finding:state'] = 'The review status set by the reviewer.';
$string['privacy:metadata:finding:timereviewed'] = 'When the finding was last reviewed.';
$string['privacy:metadata:fix'] = 'Changes made to course content from the accessibility report, kept so they can be undone.';
$string['privacy:metadata:fix:courseid'] = 'The course the change was made in.';
$string['privacy:metadata:fix:newcontent'] = 'The content of the changed text area after the change. It is course content and may include text written by other users, for example a glossary entry. It is deleted when that content is deleted, or after the retention period.';
$string['privacy:metadata:fix:oldcontent'] = 'The content of the changed text area before the change, kept so the change can be undone. It is course content and may include text written by other users, for example a glossary entry. It is deleted when that content is deleted, or after the retention period.';
$string['privacy:metadata:fix:summary'] = 'A description of the change, including any text entered (such as alternative text).';
$string['privacy:metadata:fix:timecreated'] = 'When the change was made.';
$string['privacy:metadata:fix:timeundone'] = 'When the change was undone.';
$string['privacy:metadata:fix:undoneby'] = 'The user who undid the change.';
$string['privacy:metadata:fix:userid'] = 'The user who made the change.';
$string['privacy:metadata:history'] = 'History of review actions on findings and inventory items.';
$string['privacy:metadata:history:action'] = 'The action taken.';
$string['privacy:metadata:history:courseid'] = 'The course.';
$string['privacy:metadata:history:note'] = 'Notes written by the reviewer.';
$string['privacy:metadata:history:timecreated'] = 'When the action happened.';
$string['privacy:metadata:history:userid'] = 'The user who took the action.';
$string['privacy:metadata:linkchecks'] = 'When link checking is enabled, external URLs found in course content are requested from their servers. No user data, cookies or credentials are sent.';
$string['privacy:metadata:linkchecks:url'] = 'The URL being checked.';
$string['privacy:metadata:res'] = 'Manual reviews of PDFs, videos and SCORM packages.';
$string['privacy:metadata:res:courseid'] = 'The course.';
$string['privacy:metadata:res:reviewerid'] = 'The user who completed the review.';
$string['privacy:metadata:res:reviewnotes'] = 'Notes written by the reviewer.';
$string['privacy:metadata:res:timereviewed'] = 'When the review was saved.';
$string['privacy:metadata:scan'] = 'Scan requests.';
$string['privacy:metadata:scan:courseid'] = 'The course scanned.';
$string['privacy:metadata:scan:requestedby'] = 'The user who queued the scan.';
$string['privacy:metadata:scan:timecreated'] = 'When the scan was queued.';
$string['privacy:metadata:tandreig_license'] = 'The licence service validates the site licence.';
$string['privacy:metadata:tandreig_license:domain'] = 'The Moodle site host.';
$string['privacy:metadata:tandreig_license:key'] = 'The site licence key.';
$string['progress'] = 'Progress over time';
$string['progress_caption'] = 'Counts after each completed scan, newest first';
$string['progress_change_down'] = '{$a->what} down by {$a->n}';
$string['progress_change_same'] = '{$a->what} unchanged';
$string['progress_change_up'] = '{$a->what} up by {$a->n}';
$string['progress_none'] = 'Progress is recorded after each completed scan. Run a scan to start.';
$string['progress_noscore'] = 'Counts are shown instead of a percentage score, because a score from automated checks would suggest more than the checks can measure.';
$string['progress_since'] = 'Since {$a}:';
$string['provider_activity_intro'] = 'Activity descriptions and text and media areas';
$string['provider_book_chapter'] = 'Book chapters';
$string['provider_book_label'] = '{$a->book} › {$a->chapter}';
$string['provider_course_summary'] = 'Course summary';
$string['provider_glossary_entry'] = 'Glossary entries';
$string['provider_glossary_label'] = '{$a->glossary} › {$a->entry}';
$string['provider_lesson_label'] = '{$a->lesson} › {$a->page}';
$string['provider_lesson_page'] = 'Lesson pages';
$string['provider_page_content'] = 'Page content';
$string['provider_question_label'] = '{$a->quiz} › {$a->question}';
$string['provider_quiz_question'] = 'Quiz questions';
$string['provider_section_label'] = 'Section: {$a}';
$string['provider_section_summary'] = 'Section summaries';
$string['provider_url_resource'] = 'URL resources';
$string['queueall'] = 'Queue scans for all courses';
$string['queuebulk_help'] = 'Scans run in the background through cron. Courses that already have a scan queued or running are skipped.';
$string['queuecategory'] = 'Queue scans for all courses in this category';
$string['queuescan'] = 'Queue scan';
$string['queuescan_help'] = 'The scan runs in the background through cron and does not change course content. Refresh this page to see progress.';
$string['readonlynotice'] = 'Changing the review status here does not change course content. To correct the content, use "Fix this" where it is offered, or the Edit link, then rescan.';
$string['reason'] = 'Reason';
$string['reason_authrequired'] = 'The server requires authentication (not treated as broken).';
$string['reason_blocked_blocked'] = 'Not checked: the address is not permitted.';
$string['reason_blocked_credentials'] = 'Not checked: the URL contains credentials.';
$string['reason_blocked_dns'] = 'Not checked: the host name could not be resolved.';
$string['reason_blocked_invalidhost'] = 'Not checked: the host name is not valid.';
$string['reason_blocked_invalidurl'] = 'Not checked: the URL is not valid.';
$string['reason_blocked_port'] = 'Not checked: the port is not permitted.';
$string['reason_blocked_privateaddress'] = 'Not checked: the address is internal or private.';
$string['reason_blocked_scheme'] = 'Not checked: only http and https links are checked.';
$string['reason_blocked_siteblocked'] = 'Not checked: blocked by the site\'s cURL security settings.';
$string['reason_blocked_toomanyredirects'] = 'Not verified: too many redirects.';
$string['reason_connection'] = 'Could not connect.';
$string['reason_dns'] = 'The host name does not exist (confirmed by repeated checks).';
$string['reason_dnspending'] = 'The host name could not be resolved; it will be checked again before being reported as unavailable.';
$string['reason_forbidden'] = 'Access is forbidden to automated checks (not treated as broken).';
$string['reason_gone'] = 'The page has been removed (HTTP 410).';
$string['reason_httpother'] = 'Unexpected response.';
$string['reason_notfound'] = 'Page not found (confirmed by repeated checks).';
$string['reason_notfoundpending'] = 'Page not found; it will be checked again before being reported as unavailable.';
$string['reason_ok'] = 'OK';
$string['reason_ratelimited'] = 'The server limited the number of requests. It will be retried later.';
$string['reason_redirected'] = 'OK after redirect.';
$string['reason_servererror'] = 'Server error (may be temporary).';
$string['reason_ssl'] = 'Secure connection (TLS) problem.';
$string['reason_timeout'] = 'The request timed out.';
$string['reason_toomanyredirects'] = 'Too many redirects.';
$string['reason_videorestricted'] = 'The video is private or embedding is restricted (not treated as broken).';
$string['reason_vimeodomain'] = 'Restricted to specific websites: the Vimeo player refused to play it for this site. It may still play inside Moodle if your production site is on the video\'s allowed list. Check it in the course.';
$string['reason_vimeogone'] = 'Vimeo and the Vimeo API both report the video does not exist.';
$string['reason_vimeonotfound'] = 'Vimeo reports "not found". This can mean the video was deleted or that it is private. Verify manually or configure a Vimeo API token.';
$string['reason_vimeoplayerok'] = 'The Vimeo player loads for this site (the video is embedded with domain or privacy restrictions).';
$string['reason_vimeorestricted'] = 'The video is private or restricted to specific domains (not treated as broken).';
$string['rescandelay'] = 'Delay before rescanning after an edit (minutes)';
$string['rescandelay_desc'] = 'Edits made within this time are checked together in one scan.';
$string['rescanonedit'] = 'Rescan courses after edits';
$string['rescanonedit_desc'] = 'Queue a scan of a course shortly after its content is created or edited (course and section summaries, activities, book chapters, glossary entries, lesson pages and quiz questions), so new content is checked soon after it is added.';
$string['resourcereview'] = 'Manual review';
$string['retentiondays'] = 'Retention (days)';
$string['retentiondays_desc'] = 'Scan records, and findings or inventory items that have been absent for longer than this, are deleted with their history. Fix records, with the content kept for undo, are deleted after this period too. The latest scan of each course is always kept.';
$string['review'] = 'Review';
$string['review_inprogress'] = 'Review in progress';
$string['review_issuesfound'] = 'Reviewed: issues found';
$string['review_notapplicable'] = 'Not applicable';
$string['review_notreviewed'] = 'Not reviewed';
$string['review_reviewed'] = 'Reviewed: no issues found';
$string['reviewaction'] = 'Review';
$string['reviewitem'] = 'Review {$a}';
$string['reviewoutcome'] = 'Review outcome';
$string['reviewsaved'] = 'Review saved.';
$string['reviewstatus'] = 'Review status';
$string['reviewstatus_help'] = 'Open: needs attention. In progress: someone is working on it. Resolved: fixed (the next scan confirms this). Accepted exception / not applicable: reviewed and intentionally left as is; a note is required. If the source content changes, resolved findings and exceptions are reopened automatically.';
$string['savereview'] = 'Save review';
$string['scanalreadyqueued'] = 'A scan is already queued or running for this course.';
$string['scanerrors'] = 'Problems reported by the scan:';
$string['scanprogress'] = '{$a->status}: {$a->items} item(s) processed (queued {$a->time}).';
$string['scanqueued'] = 'Scan queued. It will run in the background.';
$string['scanstatus'] = 'Scan status';
$string['scanstatus_queued'] = 'Queued';
$string['scanstatus_running'] = 'Running';
$string['scorm_noautomated'] = 'No automated accessibility checks are performed on SCORM packages.';
$string['scormversion'] = 'SCORM version: {$a}';
$string['sectionnumber'] = 'Section {$a}';
$string['settings_fixes'] = 'Fixing content';
$string['settings_fixes_desc'] = 'Fixing content from the report changes course content. It is off by default.';
$string['settings_links'] = 'Link and video availability';
$string['settings_links_desc'] = 'Link availability is a separate category from accessibility.';
$string['settings_pdf'] = 'Optional PDF analysis';
$string['settings_pdf_desc'] = 'Optional. Requires Poppler utilities (for example the poppler-utils package on Debian/Ubuntu).';
$string['settings_scan'] = 'Scanning';
$string['settings_scan_desc'] = 'Scans read stored course content and never change it.';
$string['severity'] = 'Severity';
$string['severity_error'] = 'Error';
$string['severity_error_plural'] = 'Errors';
$string['severity_notice'] = 'Notice';
$string['severity_notice_plural'] = 'Notices';
$string['severity_warning'] = 'Warning';
$string['severity_warning_plural'] = 'Warnings';
$string['sitereport'] = 'Accessibility review';
$string['size'] = 'Size';
$string['source'] = 'Source';
$string['source_external'] = 'External link';
$string['source_file'] = 'Uploaded file';
$string['source_package'] = 'SCORM package';
$string['source_vimeo'] = 'Vimeo';
$string['source_youtube'] = 'YouTube';
$string['state_active'] = 'Open or in progress';
$string['state_exception'] = 'Accepted exception / not applicable';
$string['state_inprogress'] = 'In progress';
$string['state_open'] = 'Open';
$string['state_resolved'] = 'Resolved';
$string['status'] = 'Status';
$string['status_failed'] = 'Scan incomplete';
$string['status_failed_desc'] = 'The scan did not finish. See the messages below.';
$string['status_incomplete'] = 'Scan incomplete';
$string['status_incomplete_desc'] = 'The last scan finished, but some items could not be scanned. Results may be incomplete.';
$string['status_inprogress'] = 'Scan in progress';
$string['status_inprogress_desc'] = 'A scan is queued or running. Results appear when it completes.';
$string['status_issues'] = 'Issues to review';
$string['status_issues_desc'] = 'The last scan found items that are open or in progress.';
$string['status_manuallyreviewed'] = 'Manually reviewed';
$string['status_manuallyreviewed_desc'] = 'No open findings, and every inventoried PDF, video and SCORM package has a completed manual review. This is a record of review activity, not a certification.';
$string['status_noautomatedissues'] = 'No automated issues detected';
$string['status_noautomatedissues_desc'] = 'The automated checks found nothing open. Manual review is still needed.';
$string['status_notscanned'] = 'Not scanned';
$string['status_notscanned_desc'] = 'This course has not been scanned.';
$string['studentguide'] = 'Making course content work for you';
$string['studentguide_captions'] = 'Captions on videos';
$string['studentguide_captions_text'] = 'Most video players have a CC or subtitles button. In Vimeo and YouTube, select CC in the player controls; you can often change the caption size and colour in the player settings.
If a video in your course has no captions or transcript, tell your tutor.';
$string['studentguide_contrast'] = 'Colours and contrast';
$string['studentguide_contrast_text'] = 'Windows: Settings > Accessibility > Contrast themes. Mac: System Settings > Accessibility > Display > Increase contrast.
Browser extensions such as high-contrast or dark-mode add-ons can also change page colours.';
$string['studentguide_documents'] = 'PDFs and documents';
$string['studentguide_documents_text'] = 'Open PDFs in Microsoft Edge to use Read aloud, or in Adobe Acrobat Reader to use View > Read Out Loud and Reflow.
If a document cannot be read aloud or selected as text, it may be a scanned image: ask your tutor for an accessible version.';
$string['studentguide_help'] = 'Getting help';
$string['studentguide_help_text'] = 'If something in a course is hard for you to use, tell your tutor or student support. They can provide content in another format.';
$string['studentguide_intro'] = 'You can change how course pages look and sound without any special software. These tips work on most computers and phones.';
$string['studentguide_keyboard'] = 'Using a keyboard';
$string['studentguide_keyboard_text'] = 'Press Tab to move between links and buttons, Shift+Tab to go back, and Enter to follow a link. Most Moodle pages work fully with the keyboard.';
$string['studentguide_readaloud'] = 'Having text read aloud';
$string['studentguide_readaloud_text'] = 'Microsoft Edge: right-click the page and choose "Read aloud", or press Ctrl+Shift+U.
Chrome: install a read-aloud extension, or use Reading mode, which has a play button.
Phones: iPhone "Speak Screen" (Settings > Accessibility > Spoken Content) and Android "Select to Speak" read the screen aloud.';
$string['studentguide_readermode'] = 'Reader mode';
$string['studentguide_readermode_text'] = 'Reader mode shows just the text of a page, in a font, size and colour you choose.
Firefox and Safari: select the reader icon in the address bar. Edge: Immersive Reader (F9). Chrome: right-click and choose "Open in reading mode".';
$string['studentguide_screenreader'] = 'Screen readers';
$string['studentguide_screenreader_text'] = 'Screen readers read everything on the screen aloud. Free options: NVDA (Windows), Narrator (built into Windows), VoiceOver (built into Mac and iPhone) and TalkBack (built into Android).';
$string['studentguide_zoom'] = 'Making text bigger';
$string['studentguide_zoom_text'] = 'Press Ctrl and + (Cmd and + on a Mac) to zoom in, Ctrl and - to zoom out, and Ctrl and 0 to reset. Moodle pages reflow so you do not need to scroll sideways.
You can also set a larger default text size in your browser settings.';
$string['suggest_videotitle'] = 'Video: {$a}';
$string['suggestedfix'] = 'Suggested correction';
$string['summarybycategory'] = 'Open findings by category and severity';
$string['systemuser'] = 'Automated scan';
$string['tab_courses'] = 'Courses';
$string['tab_findings'] = 'Findings';
$string['tab_fixes'] = 'Fixes';
$string['tab_links'] = 'Link availability';
$string['tab_overview'] = 'Overview';
$string['tab_pdf'] = 'PDFs';
$string['tab_scorm'] = 'SCORM';
$string['tab_video'] = 'Videos';
$string['task_analysepdfs'] = 'Analyse PDFs for accessibility signals';
$string['task_checklinks'] = 'Check link and video availability';
$string['task_cleanup'] = 'Accessibility review housekeeping';
$string['task_renewlicense'] = 'Refresh the site licence';
$string['task_scancourse'] = 'Scan course content for accessibility issues';
$string['task_scheduledscan'] = 'Queue scheduled accessibility scans';
$string['type'] = 'Type';
$string['type_automated'] = 'Automated';
$string['type_automated_long'] = 'Automated: detected reliably by the scanner';
$string['type_manual'] = 'Needs review';
$string['type_manual_long'] = 'Needs human review: the scanner cannot decide without context';
$string['unclearlinktext'] = 'Unclear link text';
$string['unclearlinktext_desc'] = 'Link texts (one per line) flagged for contextual review.';
$string['undofix'] = 'Undo';
$string['undofixfor'] = 'Undo fix in {$a}';
$string['unknownlocation'] = 'Unknown location';
$string['url'] = 'URL';
$string['usedin'] = 'Used in';
$string['user'] = 'User';
$string['useragent'] = 'User-Agent';
$string['useragent_desc'] = 'User-Agent header sent with link checks.';
$string['viewsource'] = 'View';
$string['vimeotoken'] = 'Vimeo API access token (optional)';
$string['vimeotoken_desc'] = 'Optional personal access token with "public" and "private" scopes for the account that owns your videos. Enables caption-track evidence and confirmation of deleted videos. Without it, captions are always reported as unknown. The token is never shown in reports or logs.';
$string['vimeovideo'] = 'Vimeo video {$a}';
$string['wcag'] = 'WCAG reference';
$string['wcag_criterion'] = 'WCAG {$a->sc} (level {$a->level}) · EN 301 549 clause {$a->en}';
$string['wcag_none'] = 'Good practice (no direct WCAG success criterion)';
$string['wcag_refs'] = 'WCAG 2.2 success criterion {$a}';
$string['youtubevideo'] = 'YouTube video {$a}';
