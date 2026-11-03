<?php
/**
 * api/household_helpers.php
 *
 * Shared by register.php and profile.php:
 *   - the 30 barangays of Malita, Davao Occidental
 *   - household key building (one resident account per household)
 *   - apply_resident_address_update(): saves a resident's edited barangay +
 *     household address from the profile screen, with the same duplicate check.
 */

const MALITA_BARANGAYS = [
    'Bito', 'Bolila', 'Buhangin', 'Culaman', 'Datu Danwata', 'Demoloc',
    'Felis', 'Fishing Village', 'Kibalatong', 'Kidalapong', 'Kilalag',
    'Kinangan', 'Lacaron', 'Lagumit', 'Lais', 'Little Baguio', 'Macol',
    'Mana', 'Manuel Peralta', 'New Argao', 'Pangaleon', 'Pangian',
    'Pinalpalan', 'Poblacion', 'Sangay', 'Talogoy', 'Tical', 'Ticulon',
    'Tingolo', 'Tubalan',
];

/** Lowercase, drop punctuation, collapse spaces ("Blk 3, Lot 5" == "block 3 lot 5"). */
function norm_part(string $s, array $dropWords = []): string
{
    $s = mb_strtolower(trim($s));
    $s = preg_replace('/\bblk\b/u', 'block', $s);
    foreach ($dropWords as $w) {
        $s = preg_replace('/\b' . preg_quote($w, '/') . '\b/u', ' ', $s);
    }
    $s = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s);
    return trim($s);
}

/** Official spelling of a Malita barangay, or null if the input is not one. */
function canonical_barangay(string $input): ?string
{
    $want = norm_part($input);
    foreach (MALITA_BARANGAYS as $b) {
        if (norm_part($b) === $want) return $b;
    }
    return null;
}

/** barangay + address -> unique household key, or null if the address is unusable. */
function make_household_key(string $barangay, string $address): ?string
{
    $addrN = norm_part($address);
    if ($addrN === '') return null;
    $key = norm_part($barangay) . '|' . $addrN;
    return mb_strlen($key) > 255 ? null : $key;
}

/**
 * Saves an edited barangay + household address for resident $residentId.
 * Returns null on success, or a user-facing error message on failure
 * (invalid barangay, bad address, or the household already has an account).
 */
function apply_resident_address_update(mysqli $conn, int $residentId, string $barangay, string $address): ?string
{
    $barangay = trim($barangay);
    $address  = trim($address);

    $canon = canonical_barangay($barangay);
    if ($canon === null) {
        return 'Please select a barangay in Malita, Davao Occidental.';
    }
    if (mb_strlen($address) < 3 || mb_strlen($address) > 200) {
        return 'Household address must be between 3 and 200 characters.';
    }
    $key = make_household_key($canon, $address);
    if ($key === null) {
        return 'Please enter a valid household address or landmark.';
    }

    // Another resident already registered this household?
    $c = $conn->prepare('SELECT id FROM resident WHERE household_key = ? AND id <> ? LIMIT 1');
    $c->bind_param('si', $key, $residentId);
    $c->execute();
    if ($c->get_result()->fetch_assoc()) {
        return 'A resident account already exists for this household. Only one family member can register per household.';
    }

    try {
        $u = $conn->prepare('UPDATE resident SET barangay = ?, household_address = ?, household_key = ? WHERE id = ?');
        $u->bind_param('sssi', $canon, $address, $key, $residentId);
        if (!$u->execute()) {
            throw new mysqli_sql_exception($u->error, $u->errno);
        }
    } catch (Throwable $e) {
        error_log('apply_resident_address_update failed: ' . $e->getMessage());
        if ((int) $e->getCode() === 1062) {
            return 'A resident account already exists for this household. Only one family member can register per household.';
        }
        return 'Could not save your address. Please try again.';
    }
    return null;
}
