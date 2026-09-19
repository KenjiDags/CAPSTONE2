<?php
function mrpDate(?string $value): ?DateTimeImmutable {
    if (!$value) return null;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', substr($value, 0, 10), new DateTimeZone('Asia/Manila'));
    $errors = DateTimeImmutable::getLastErrors();
    return $date && (!$errors || (!$errors['warning_count'] && !$errors['error_count'])) ? $date : null;
}
function mrpDaysOutOfStock(?string $stockoutDate, ?string $currentDate = null): ?int {
    $start = mrpDate($stockoutDate);
    $today = $currentDate === null ? new DateTimeImmutable('today', new DateTimeZone('Asia/Manila')) : mrpDate($currentDate);
    return $start && $today ? max(0, (int)$start->diff($today)->format('%r%a')) : null;
}
function mrpExpectedResolution(?string $stockoutDate, ?int $leadTimeDays): ?string {
    $start = mrpDate($stockoutDate);
    return $start && $leadTimeDays !== null && $leadTimeDays >= 0
        ? $start->modify('+' . $leadTimeDays . ' days')->format('Y-m-d') : null;
}
