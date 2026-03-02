<?php namespace ProcessWire;

/**
 * Date Range Field
 * 
 * This file is part of the ProFields package
 * Please do not distribute.
 * 
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
 * https://processwire.com
 *
 * Fieldtype settings
 * ------------------
 * @property FieldtypeDateRange $type
 * @property string $dateOutputFormat
 * @property string $dateRangeSeparator
 * @property int|bool $collapseRange
 * 
 * Inputfield module settings
 * --------------------------
 * @property bool $dayLabelMode Refer to 'days' rather than 'nights' in labels?
 * @property string $outputStyle Output style, one of 'admin' or blank for stock
 *
 * Inputfield Hotel Datepicker settings
 * ------------------------------------
 * @property bool $inline Use inline mode where date picker is always visible?
 * @property bool $clearButton Add a button that clears the selected date range
 * @property bool $showTopbar Show the top bar?
 * @property bool $autoClose Close the datepicker after selection?
 * @property bool $selectForward Don't allow selections in both directions (first click = first date, second click = second date)
 * @property bool $enableCheckout Enable checkout on disabled dates?
 * @property bool $moveBothMonths Move both months when clicking on the next/prev month button
 * @property string $topbarPosition Location of top bar, specify 'bottom' or 'top'
 * @property string $format Date format to use in Fecha format, i.e. 'YYYY-MM-DD'
 * @property string $startOfWeek Day that starts the week, i.e. 'sunday'
 * @property int $minNights Mininum number of nights
 * @property int $maxNights Maximum number of nights
 * @property string $startDate Don't allow ranges before custom date i.e. 2023-11-21
 * @property string $endDate Don't allow ranges after custom date i.e. 2023-12-11
 * @property array $disabledDates Dates that are not selectable, i.e. [ '2023-11-23', '2023-11-19' ]
 * @property array $disabledDaysOfWeek Disabled week days, i.e. [ 'Monday', 'Tuesday' ]
 * @property array $noCheckInDaysOfWeek Disable check-in on specific days of week, i.e. [ 'Monday', 'Tuesday' ]
 * @property array $noCheckOutDaysOfWeek Disable check-out on specific days of week, i.e. [ 'Monday', 'Tuesday' ]
 * 
 */
class DateRangeField extends Field { }