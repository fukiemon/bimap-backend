import 'dart:async';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:geocoding/geocoding.dart';
import 'package:geolocator/geolocator.dart';
import 'package:image_picker/image_picker.dart';
import '../../services/api_service.dart';
import '../../services/login_screen.dart';

class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key});

  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  final _formKey = GlobalKey<FormState>();

  final _firstNameController = TextEditingController();
  final _lastNameController = TextEditingController();
  final _phoneController = TextEditingController();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  final _confirmPasswordController = TextEditingController();
  final _licenseNumberController = TextEditingController();
  final _locationController = TextEditingController();

  // Driver location: filled in automatically (with the phone's permission)
  // when someone picks the Driver role. The driver can edit it.
  bool _locating = false;
  String? _locationNote;

  // Resident: optional button that picks the barangay from the phone's location.
  bool _detectingBarangay = false;
  String? _barangayNote;

  final ImagePicker _picker = ImagePicker();

  /// Phone verification needs an SMS provider (e.g. Semaphore), which is paid.
  /// While this is false, registration is email-only. Set it to true again
  /// if an SMS provider is connected to the backend.
  static const bool _smsEnabled = false;

  String _contactMethod = _smsEnabled ? 'mobile' : 'email'; // 'mobile' | 'email'
  String _role = 'resident'; // 'resident' | 'driver'

  static const List<String> _barangays = [
    'Bito',
    'Bolila',
    'Buhangin',
    'Culaman',
    'Datu Danwata',
    'Demoloc',
    'Felis',
    'Fishing Village',
    'Kibalatong',
    'Kidalapong',
    'Kilalag',
    'Kinangan',
    'Lacaron',
    'Lagumit',
    'Lais',
    'Little Baguio',
    'Macol',
    'Mana',
    'Manuel Peralta',
    'New Argao',
    'Pangian',
    'Pinalpalan',
    'Poblacion',
    'Sangay',
    'Talogoy',
    'Tical',
    'Ticulon',
    'Tingolo',
    'Tubalan',
    'Pangaleon',
  ];

  String? _selectedBarangay;

  XFile? _licensePhoto;

  bool _loading = false;
  bool _obscurePassword = true;
  bool _obscureConfirm = true;
  String? _error;
  String? _success;

  static const Color primaryBlue = Color(0xFF1A73E8);
  static const Color darkBlue = Color(0xFF0D47A1);
  static const Color bimapGreen = Color(0xFF3C9A3C);
  static const Color driverTeal = Color(0xFF0F9D8C);
  static const Color driverTealBg = Color(0xFFEAF7F5);
  static const Color mutedText = Color(0xFF6B7A99);
  static const Color fieldFill = Color(0xFFF4F7FC);

  // ---------------------------------------------------------------------
  // Phone helpers
  // A PH mobile number is +63 followed by 10 digits starting with 9
  // (e.g. +63 917 123 4567). The user may type 917..., 0917... or 63917...;
  // we clean it to just the 10 digits.
  // ---------------------------------------------------------------------
  String _normalizedPhone() {
    var digits = _phoneController.text.replaceAll(RegExp(r'\D'), '');
    if (digits.startsWith('63')) {
      digits = digits.substring(2);
    } else if (digits.startsWith('0')) {
      digits = digits.substring(1);
    }
    return digits;
  }

  bool _isValidPhone(String digits) => RegExp(r'^9\d{9}$').hasMatch(digits);

  // The exact contact string used for the OTP endpoints.
  String _currentContactValue() {
    return _contactMethod == 'mobile'
        ? '+63${_normalizedPhone()}'
        : _emailController.text.trim();
  }

  // ---------------------------------------------------------------------
  // Driver location
  // Picking the Driver role asks for location permission, reads the phone's
  // GPS and turns it into a readable address. It is saved with the driver's
  // application so the admin can see it before approving. If permission is
  // denied or the address can't be found, the driver can type it in.
  // ---------------------------------------------------------------------
  void _selectRole(String value) {
    setState(() => _role = value);
    if (value == 'driver' && _locationController.text.trim().isEmpty) {
      _detectLocation();
    }
  }

  void _failLocation(String message) {
    if (!mounted) return;
    setState(() {
      _locating = false;
      _locationNote = message;
    });
  }

  Future<void> _detectLocation() async {
    if (_locating) return;
    setState(() {
      _locating = true;
      _locationNote = null;
    });

    try {
      final enabled = await Geolocator.isLocationServiceEnabled();
      if (!enabled) {
        _failLocation('Turn on Location (GPS) on this phone, or type your address.');
        return;
      }

      var permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }
      if (permission == LocationPermission.denied ||
          permission == LocationPermission.deniedForever) {
        _failLocation('Location permission was not allowed. You can type your address instead.');
        return;
      }

      final pos = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
          timeLimit: Duration(seconds: 20),
        ),
      );
      final coords =
          '${pos.latitude.toStringAsFixed(5)}, ${pos.longitude.toStringAsFixed(5)}';

      // Turn the GPS point into a street / barangay / town address.
      String address = '';
      try {
        final marks = await placemarkFromCoordinates(pos.latitude, pos.longitude);
        if (marks.isNotEmpty) {
          final p = marks.first;
          final parts = <String>[
            p.street ?? '',
            p.subLocality ?? '',
            p.locality ?? '',
            p.subAdministrativeArea ?? '',
            p.administrativeArea ?? '',
          ].map((e) => e.trim()).where((e) => e.isNotEmpty).toList();
          address = parts.toSet().join(', ');
        }
      } catch (_) {
        // No address found: fall back to the coordinates below.
      }

      var text = address.isNotEmpty ? '$address ($coords)' : coords;
      if (text.length > 190) text = text.substring(0, 190);

      if (!mounted) return;
      setState(() {
        _locationController.text = text;
        _locating = false;
        _locationNote = address.isNotEmpty
            ? 'Detected from your phone. You can edit it.'
            : 'No street name was found, so your GPS coordinates were filled in. You can edit it.';
      });
    } catch (e) {
      _failLocation('Could not get your location. You can type your address instead.');
    }
  }

  // ---------------------------------------------------------------------
  // Resident: pick the barangay from the phone's location (optional).
  // Only the barangay dropdown is filled in. The coordinates and address
  // are NOT saved anywhere. If no barangay name is found in the address,
  // the resident just picks it from the list.
  // ---------------------------------------------------------------------
  Future<void> _detectBarangay() async {
    if (_detectingBarangay) return;
    setState(() {
      _detectingBarangay = true;
      _barangayNote = null;
    });

    String? note;
    String? found;
    try {
      if (!await Geolocator.isLocationServiceEnabled()) {
        note = 'Turn on Location (GPS) on this phone, or choose your barangay from the list.';
      } else {
        var permission = await Geolocator.checkPermission();
        if (permission == LocationPermission.denied) {
          permission = await Geolocator.requestPermission();
        }
        if (permission == LocationPermission.denied ||
            permission == LocationPermission.deniedForever) {
          note = 'Location permission was not allowed. Please choose your barangay from the list.';
        } else {
          final pos = await Geolocator.getCurrentPosition(
            locationSettings: const LocationSettings(
              accuracy: LocationAccuracy.high,
              timeLimit: Duration(seconds: 20),
            ),
          );
          final marks = await placemarkFromCoordinates(pos.latitude, pos.longitude);
          final text = marks
              .expand((p) => [
                    p.name,
                    p.street,
                    p.thoroughfare,
                    p.subLocality,
                    p.locality,
                    p.subAdministrativeArea,
                  ])
              .whereType<String>()
              .join(' | ')
              .toLowerCase();

          for (final b in _barangays) {
            final re = RegExp(r'\b' + RegExp.escape(b.toLowerCase()) + r'\b');
            if (re.hasMatch(text)) {
              found = b;
              break;
            }
          }

          note = found != null
              ? 'Detected: $found. Please check that it is right.'
              : "Couldn't tell your barangay from your location. Please choose it from the list.";
        }
      }
    } catch (_) {
      note = 'Could not get your location. Please choose your barangay from the list.';
    }

    if (!mounted) return;
    setState(() {
      _detectingBarangay = false;
      _barangayNote = note;
      if (found != null) _selectedBarangay = found;
    });
  }

  Future<void> _pickLicensePhoto() async {
    final file = await _picker.pickImage(source: ImageSource.gallery, imageQuality: 85);
    if (file == null) return;

    final sizeInBytes = await file.length();
    if (sizeInBytes > 5 * 1024 * 1024) {
      setState(() => _error = 'License photo must be under 5MB');
      return;
    }

    setState(() {
      _licensePhoto = file;
      _error = null;
    });
  }

  // ---------------------------------------------------------------------
  // Create Account flow:
  // 1) validate the form
  // 2) send the verification code to the email / phone
  // 3) show the "Verify Your Account" dialog
  // 4) only if the code is correct, create the account
  // ---------------------------------------------------------------------
  Future<void> _register() async {
    if (!_formKey.currentState!.validate()) return;

    if (_selectedBarangay == null) {
      setState(() => _error = 'Please select your barangay');
      return;
    }

    if (_role == 'driver') {
      if (_licenseNumberController.text.trim().isEmpty) {
        setState(() => _error = "Please enter your driver's license number");
        return;
      }
      if (_licensePhoto == null) {
        setState(() => _error = 'Please upload a photo of your license');
        return;
      }
    }

    setState(() {
      _loading = true;
      _error = null;
      _success = null;
    });

    final api = ApiService();
    final contact = _currentContactValue();

    try {
      // Step 1: send the code.
      final type = await api.sendVerificationCode(contact);
      if (!mounted) return;
      setState(() => _loading = false);

      // Step 2: ask the user to enter the code.
      final verified = await showDialog<bool>(
        context: context,
        barrierDismissible: false,
        builder: (_) => _OtpDialog(contact: contact, contactType: type),
      );

      if (verified != true) return; // cancelled
      if (!mounted) return;

      // Step 3: code is correct, create the account.
      setState(() => _loading = true);

      await api.register(
        firstName: _firstNameController.text.trim(),
        lastName: _lastNameController.text.trim(),
        contactMethod: _contactMethod,
        // ApiService.register() builds the phone as '+639$contactValue',
        // so for mobile we pass the 9 digits that come after the leading 9.
        // This gives the same '+63' + 10 digits string that was verified.
        contactValue: _contactMethod == 'mobile'
            ? _normalizedPhone().substring(1)
            : _emailController.text.trim(),
        barangay: _selectedBarangay!,
        password: _passwordController.text,
        role: _role,
        driverLicenseNumber:
            _role == 'driver' ? _licenseNumberController.text.trim() : null,
        driverLicensePhoto:
            _role == 'driver' && _licensePhoto != null ? File(_licensePhoto!.path) : null,
        location: _role == 'driver' ? _locationController.text.trim() : null,
      );

      if (!mounted) return;

      setState(() {
        _success = _role == 'driver'
            ? 'Account created! Your driver application is pending admin approval.'
            : 'Account created successfully! You can now log in.';
      });

      await Future.delayed(const Duration(seconds: 2));
      if (!mounted) return;

      Navigator.pushReplacement(
        context,
        MaterialPageRoute(builder: (_) => const LoginScreen()),
      );
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = e.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  void dispose() {
    _firstNameController.dispose();
    _lastNameController.dispose();
    _phoneController.dispose();
    _emailController.dispose();
    _passwordController.dispose();
    _confirmPasswordController.dispose();
    _licenseNumberController.dispose();
    _locationController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: fieldFill,
      body: SafeArea(
        child: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _buildHeader(),
              Transform.translate(
                offset: const Offset(0, -24),
                child: Container(
                  margin: const EdgeInsets.symmetric(horizontal: 20),
                  padding: const EdgeInsets.all(24),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(20),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withOpacity(0.08),
                        blurRadius: 20,
                        offset: const Offset(0, 8),
                      ),
                    ],
                  ),
                  child: _buildForm(),
                ),
              ),
              const SizedBox(height: 24),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildHeader() {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 32, horizontal: 24),
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: [darkBlue, primaryBlue],
        ),
        borderRadius: BorderRadius.only(
          bottomLeft: Radius.circular(32),
          bottomRight: Radius.circular(32),
        ),
      ),
      child: Column(
        children: [
          Row(
            children: [
              IconButton(
                onPressed: () => Navigator.pop(context),
                icon: const Icon(Icons.arrow_back, color: Colors.white),
              ),
            ],
          ),
          Image.asset(
            'assets/images/bimap_pin_logo.png',
            width: 100,
            height: 100,
            fit: BoxFit.contain,
          ),
          const SizedBox(height: 8),
          RichText(
            text: TextSpan(
              style: const TextStyle(
                fontSize: 26,
                fontWeight: FontWeight.w900,
                letterSpacing: 0.5,
              ),
              children: [
                const TextSpan(text: 'Bi', style: TextStyle(color: primaryBlue)),
                TextSpan(text: 'MAP', style: TextStyle(color: bimapGreen)),
              ],
            ),
          ),
          const SizedBox(height: 4),
          const Text(
            'Barangay Integrated Monitoring and Administrative Platform',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: Colors.white70,
              fontSize: 12,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildForm() {
    return Form(
      key: _formKey,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Create Account 🎉',
            style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: Color(0xFF0D1B2A)),
          ),
          const SizedBox(height: 4),
          const Text(
            'Join BiMAP to manage garbage collection',
            style: TextStyle(fontSize: 13, color: mutedText, fontWeight: FontWeight.w600),
          ),
          const SizedBox(height: 20),

          // First / last name
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _buildLabel('FIRST NAME'),
                    const SizedBox(height: 6),
                    TextFormField(
                      controller: _firstNameController,
                      decoration: _inputDecoration(hint: 'e.g. Maria'),
                      validator: (v) =>
                          (v == null || v.trim().isEmpty) ? 'Required' : null,
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _buildLabel('LAST NAME'),
                    const SizedBox(height: 6),
                    TextFormField(
                      controller: _lastNameController,
                      decoration: _inputDecoration(hint: 'e.g. Santos'),
                      validator: (v) =>
                          (v == null || v.trim().isEmpty) ? 'Required' : null,
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),

          _buildLabel(_smsEnabled ? 'CONTACT INFO' : 'EMAIL'),
          const SizedBox(height: 6),
          if (_smsEnabled) ...[
            _buildContactToggle(),
            const SizedBox(height: 12),
          ],
          _contactMethod == 'mobile' ? _buildMobileField() : _buildEmailField(),
          const SizedBox(height: 16),

          _buildLabel('BARANGAY'),
          const SizedBox(height: 6),
          _buildBarangayDropdown(),
          if (_role == 'resident') ...[
            const SizedBox(height: 8),
            OutlinedButton.icon(
              onPressed: _detectingBarangay ? null : _detectBarangay,
              icon: _detectingBarangay
                  ? const SizedBox(
                      width: 14,
                      height: 14,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.my_location_rounded, size: 16),
              label: Text(_detectingBarangay ? 'Checking...' : 'Use my location to pick my barangay'),
              style: OutlinedButton.styleFrom(
                foregroundColor: primaryBlue,
                side: const BorderSide(color: primaryBlue),
              ),
            ),
            const SizedBox(height: 4),
            Text(
              _barangayNote ?? 'Optional. Your location is only used to pick the barangay and is not saved.',
              style: const TextStyle(fontSize: 11, color: mutedText, fontWeight: FontWeight.w600),
            ),
          ],
          const SizedBox(height: 16),

          _buildLabel('PASSWORD'),
          const SizedBox(height: 6),
          TextFormField(
            controller: _passwordController,
            obscureText: _obscurePassword,
            decoration: _inputDecoration(hint: 'Min. 6 characters').copyWith(
              suffixIcon: IconButton(
                icon: Icon(
                  _obscurePassword ? Icons.visibility_outlined : Icons.visibility_off_outlined,
                  color: Colors.grey,
                  size: 20,
                ),
                onPressed: () => setState(() => _obscurePassword = !_obscurePassword),
              ),
            ),
            validator: (v) {
              if (v == null || v.isEmpty) return 'Please enter a password';
              if (v.length < 6) return 'Password must be at least 6 characters';
              return null;
            },
          ),
          const SizedBox(height: 16),

          _buildLabel('CONFIRM PASSWORD'),
          const SizedBox(height: 6),
          TextFormField(
            controller: _confirmPasswordController,
            obscureText: _obscureConfirm,
            decoration: _inputDecoration(hint: 'Re-enter password').copyWith(
              suffixIcon: IconButton(
                icon: Icon(
                  _obscureConfirm ? Icons.visibility_outlined : Icons.visibility_off_outlined,
                  color: Colors.grey,
                  size: 20,
                ),
                onPressed: () => setState(() => _obscureConfirm = !_obscureConfirm),
              ),
            ),
            validator: (v) {
              if (v != _passwordController.text) return 'Passwords do not match';
              return null;
            },
          ),
          const SizedBox(height: 20),

          _buildLabel('SELECT YOUR ROLE'),
          const SizedBox(height: 8),
          _buildRoleSelector(),

          AnimatedSize(
            duration: const Duration(milliseconds: 200),
            child: _role == 'driver' ? _buildDriverDetails() : const SizedBox.shrink(),
          ),

          if (_error != null) ...[
            const SizedBox(height: 12),
            Text(_error!, style: const TextStyle(color: Colors.red, fontWeight: FontWeight.w600)),
          ],
          if (_success != null) ...[
            const SizedBox(height: 12),
            Text(_success!,
                style: const TextStyle(color: Colors.green, fontWeight: FontWeight.w600)),
          ],

          const SizedBox(height: 24),

          SizedBox(
            width: double.infinity,
            height: 50,
            child: ElevatedButton(
              onPressed: _loading ? null : _register,
              style: ElevatedButton.styleFrom(
                backgroundColor: primaryBlue,
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(25)),
                elevation: 0,
              ),
              child: _loading
                  ? const SizedBox(
                      width: 22,
                      height: 22,
                      child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.5),
                    )
                  : const Text('Create Account',
                      style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800)),
            ),
          ),

          const SizedBox(height: 16),

          Center(
            child: GestureDetector(
              onTap: _loading
                  ? null
                  : () => Navigator.pushReplacement(
                        context,
                        MaterialPageRoute(builder: (_) => const LoginScreen()),
                      ),
              child: RichText(
                text: const TextSpan(
                  style: TextStyle(color: mutedText, fontSize: 13, fontWeight: FontWeight.w600),
                  children: [
                    TextSpan(text: 'Already have an account? '),
                    TextSpan(
                      text: 'Log in',
                      style: TextStyle(color: primaryBlue, fontWeight: FontWeight.w800),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildContactToggle() {
    return Container(
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(
        color: fieldFill,
        borderRadius: BorderRadius.circular(14),
      ),
      child: Row(
        children: [
          Expanded(child: _contactTab('mobile', Icons.phone_android_rounded, 'Mobile')),
          Expanded(child: _contactTab('email', Icons.alternate_email_rounded, 'Email')),
        ],
      ),
    );
  }

  Widget _contactTab(String value, IconData icon, String label) {
    final selected = _contactMethod == value;
    return GestureDetector(
      onTap: () => setState(() => _contactMethod = value),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 150),
        padding: const EdgeInsets.symmetric(vertical: 10),
        decoration: BoxDecoration(
          color: selected ? primaryBlue : Colors.transparent,
          borderRadius: BorderRadius.circular(10),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, size: 16, color: selected ? Colors.white : mutedText),
            const SizedBox(width: 6),
            Text(
              label,
              style: TextStyle(
                color: selected ? Colors.white : mutedText,
                fontWeight: FontWeight.w800,
                fontSize: 13,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildMobileField() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              height: 48,
              padding: const EdgeInsets.symmetric(horizontal: 14),
              decoration: const BoxDecoration(
                color: primaryBlue,
                borderRadius: BorderRadius.only(
                  topLeft: Radius.circular(12),
                  bottomLeft: Radius.circular(12),
                ),
              ),
              alignment: Alignment.center,
              child: const Text(
                '+63',
                style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 14),
              ),
            ),
            Expanded(
              child: TextFormField(
                controller: _phoneController,
                keyboardType: TextInputType.phone,
                // 12 lets people paste 63917xxxxxxx; we clean it afterwards.
                maxLength: 12,
                inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                buildCounter: (_, {required currentLength, required isFocused, maxLength}) => null,
                decoration: InputDecoration(
                  hintText: '9XX XXX XXXX',
                  hintStyle: const TextStyle(color: Color(0xFFB0BAC9), fontSize: 14),
                  filled: true,
                  fillColor: fieldFill,
                  contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                  border: const OutlineInputBorder(
                    borderRadius: BorderRadius.only(
                      topRight: Radius.circular(12),
                      bottomRight: Radius.circular(12),
                    ),
                    borderSide: BorderSide.none,
                  ),
                  focusedBorder: const OutlineInputBorder(
                    borderRadius: BorderRadius.only(
                      topRight: Radius.circular(12),
                      bottomRight: Radius.circular(12),
                    ),
                    borderSide: BorderSide(color: primaryBlue, width: 1.5),
                  ),
                  errorBorder: const OutlineInputBorder(
                    borderRadius: BorderRadius.only(
                      topRight: Radius.circular(12),
                      bottomRight: Radius.circular(12),
                    ),
                    borderSide: BorderSide(color: Colors.red, width: 1.2),
                  ),
                ),
                validator: (v) {
                  if (_contactMethod != 'mobile') return null;
                  if (v == null || v.trim().isEmpty) return 'Please enter your mobile number';
                  if (!_isValidPhone(_normalizedPhone())) {
                    return 'Enter a valid number starting with 9';
                  }
                  return null;
                },
              ),
            ),
          ],
        ),
        const SizedBox(height: 4),
        const Text(
          'Enter your 10-digit number starting with 9 (e.g. 917 123 4567)',
          style: TextStyle(fontSize: 11, color: mutedText, fontWeight: FontWeight.w600),
        ),
      ],
    );
  }

  Widget _buildEmailField() {
    return TextFormField(
      controller: _emailController,
      keyboardType: TextInputType.emailAddress,
      decoration: _inputDecoration(hint: 'you@example.com'),
      validator: (v) {
        if (_contactMethod != 'email') return null;
        if (v == null || v.trim().isEmpty) return 'Please enter your email';
        final emailRegex = RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$');
        if (!emailRegex.hasMatch(v.trim())) return 'Please enter a valid email';
        return null;
      },
    );
  }

  Widget _buildBarangayDropdown() {
    return DropdownButtonFormField<String>(
      key: ValueKey(_selectedBarangay),
      value: _selectedBarangay,
      isExpanded: true,
      decoration: _inputDecoration(hint: 'Select your barangay'),
      items: _barangays
          .map((b) => DropdownMenuItem(value: b, child: Text(b, style: const TextStyle(fontSize: 14))))
          .toList(),
      onChanged: (v) => setState(() => _selectedBarangay = v),
      validator: (v) => v == null ? 'Please select your barangay' : null,
    );
  }

  Widget _buildRoleSelector() {
    return Row(
      children: [
        Expanded(
          child: _roleCard(
            value: 'resident',
            icon: Icons.person_rounded,
            title: 'Resident',
            subtitle: 'Instant access',
            accent: primaryBlue,
            accentBg: fieldFill,
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: _roleCard(
            value: 'driver',
            icon: Icons.local_shipping_rounded,
            title: 'Driver',
            subtitle: 'Admin approval needed',
            accent: driverTeal,
            accentBg: driverTealBg,
          ),
        ),
      ],
    );
  }

  Widget _roleCard({
    required String value,
    required IconData icon,
    required String title,
    required String subtitle,
    required Color accent,
    required Color accentBg,
  }) {
    final selected = _role == value;
    return GestureDetector(
      onTap: () => _selectRole(value),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 150),
        padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 12),
        decoration: BoxDecoration(
          color: selected ? accentBg : Colors.white,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: selected ? accent : const Color(0xFFE1E7F0), width: 1.5),
        ),
        child: Column(
          children: [
            Icon(icon, color: selected ? accent : mutedText, size: 22),
            const SizedBox(height: 8),
            Text(
              title,
              style: TextStyle(
                fontWeight: FontWeight.w800,
                fontSize: 14,
                color: selected ? accent : const Color(0xFF0D1B2A),
              ),
            ),
            const SizedBox(height: 2),
            Text(
              subtitle,
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w600,
                color: selected ? accent : mutedText,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildDriverDetails() {
    return Padding(
      padding: const EdgeInsets.only(top: 16),
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: driverTealBg,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: driverTeal.withOpacity(0.3)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Row(
              children: [
                Icon(Icons.badge_rounded, size: 16, color: driverTeal),
                SizedBox(width: 6),
                Text(
                  'DRIVER DETAILS',
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w800,
                    color: driverTeal,
                    letterSpacing: 0.5,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            _buildLabel('YOUR LOCATION'),
            const SizedBox(height: 6),
            TextFormField(
              controller: _locationController,
              maxLines: 2,
              maxLength: 190,
              buildCounter: (_, {required currentLength, required isFocused, maxLength}) => null,
              decoration: _inputDecoration(hint: 'Allow location, or type your address'),
              validator: (v) {
                if (_role != 'driver') return null;
                return (v == null || v.trim().isEmpty) ? 'Required for drivers' : null;
              },
            ),
            const SizedBox(height: 8),
            OutlinedButton.icon(
              onPressed: _locating ? null : _detectLocation,
              icon: _locating
                  ? const SizedBox(
                      width: 14,
                      height: 14,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.my_location_rounded, size: 16),
              label: Text(_locating ? 'Getting location...' : 'Use my current location'),
              style: OutlinedButton.styleFrom(
                foregroundColor: driverTeal,
                side: const BorderSide(color: driverTeal),
                backgroundColor: Colors.white,
              ),
            ),
            if (_locationNote != null) ...[
              const SizedBox(height: 6),
              Text(
                _locationNote!,
                style: const TextStyle(fontSize: 11, color: mutedText, fontWeight: FontWeight.w600),
              ),
            ],
            const SizedBox(height: 4),
            const Text(
              'Your location is saved with your application so the admin can review it.',
              style: TextStyle(fontSize: 11, color: mutedText, fontWeight: FontWeight.w600),
            ),
            const SizedBox(height: 14),
            _buildLabel("DRIVER'S LICENSE NUMBER"),
            const SizedBox(height: 6),
            TextFormField(
              controller: _licenseNumberController,
              decoration: _inputDecoration(hint: 'e.g. N12-34-567890'),
              validator: (v) {
                if (_role != 'driver') return null;
                return (v == null || v.trim().isEmpty) ? 'Required for drivers' : null;
              },
            ),
            const SizedBox(height: 14),
            _buildLabel('LICENSE PHOTO'),
            const SizedBox(height: 6),
            Row(
              children: [
                OutlinedButton(
                  onPressed: _pickLicensePhoto,
                  style: OutlinedButton.styleFrom(
                    foregroundColor: const Color(0xFF0D1B2A),
                    side: const BorderSide(color: Color(0xFFCBD5E1)),
                    backgroundColor: Colors.white,
                  ),
                  child: const Text('Choose File'),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    _licensePhoto?.name ?? 'No file chosen',
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontSize: 12, color: mutedText, fontWeight: FontWeight.w600),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 6),
            const Text(
              'JPG or PNG only · Max 5MB',
              style: TextStyle(fontSize: 11, color: mutedText, fontWeight: FontWeight.w600),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildLabel(String text) {
    return Text(
      text,
      style: const TextStyle(
        fontSize: 11,
        fontWeight: FontWeight.w800,
        color: mutedText,
        letterSpacing: 0.5,
      ),
    );
  }

  InputDecoration _inputDecoration({required String hint}) {
    return InputDecoration(
      hintText: hint,
      hintStyle: const TextStyle(color: Color(0xFFB0BAC9), fontSize: 14),
      filled: true,
      fillColor: fieldFill,
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: BorderSide.none,
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: primaryBlue, width: 1.5),
      ),
      errorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: Colors.red, width: 1.2),
      ),
    );
  }
}

// =========================================================================
// "Verify Your Account" dialog. Pops with true when the code is correct,
// false when the user cancels.
// =========================================================================
class _OtpDialog extends StatefulWidget {
  final String contact;
  final String contactType; // 'email' | 'sms'

  const _OtpDialog({required this.contact, required this.contactType});

  @override
  State<_OtpDialog> createState() => _OtpDialogState();
}

class _OtpDialogState extends State<_OtpDialog> {
  static const Color primaryBlue = Color(0xFF1A73E8);
  static const Color mutedText = Color(0xFF6B7A99);
  static const Color fieldFill = Color(0xFFF4F7FC);
  static const int _cooldownSeconds = 60;

  final _codeController = TextEditingController();
  Timer? _timer;
  int _cooldown = _cooldownSeconds;
  bool _verifying = false;
  bool _resending = false;
  String? _error;
  String? _info;

  @override
  void initState() {
    super.initState();
    _startCooldown();
  }

  void _startCooldown() {
    _timer?.cancel();
    setState(() => _cooldown = _cooldownSeconds);
    _timer = Timer.periodic(const Duration(seconds: 1), (t) {
      if (!mounted) {
        t.cancel();
        return;
      }
      if (_cooldown <= 1) {
        t.cancel();
        setState(() => _cooldown = 0);
      } else {
        setState(() => _cooldown--);
      }
    });
  }

  Future<void> _resend() async {
    setState(() {
      _resending = true;
      _error = null;
      _info = null;
    });
    try {
      await ApiService().sendVerificationCode(widget.contact);
      if (!mounted) return;
      setState(() {
        _resending = false;
        _info = 'A new code has been sent.';
      });
      _startCooldown();
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _resending = false;
        _error = e.toString().replaceFirst('Exception: ', '');
      });
    }
  }

  Future<void> _verify() async {
    final code = _codeController.text.trim();
    if (code.length != 6) {
      setState(() => _error = 'Enter the 6-digit code');
      return;
    }
    setState(() {
      _verifying = true;
      _error = null;
      _info = null;
    });
    try {
      await ApiService().verifyCode(widget.contact, code);
      if (!mounted) return;
      Navigator.pop(context, true);
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _verifying = false;
        _error = e.toString().replaceFirst('Exception: ', '');
      });
    }
  }

  @override
  void dispose() {
    _timer?.cancel();
    _codeController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final via = widget.contactType == 'email' ? 'email' : 'SMS';
    final busy = _verifying || _resending;

    return AlertDialog(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      title: const Text(
        'Verify Your Account',
        style: TextStyle(fontWeight: FontWeight.w800, fontSize: 18),
      ),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'We sent a 6-digit code via $via to ${widget.contact}. Enter it below to finish creating your account.',
            style: const TextStyle(fontSize: 13, color: mutedText, fontWeight: FontWeight.w600),
          ),
          const SizedBox(height: 16),
          TextField(
            controller: _codeController,
            keyboardType: TextInputType.number,
            inputFormatters: [FilteringTextInputFormatter.digitsOnly],
            maxLength: 6,
            autofocus: true,
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontSize: 22,
              fontWeight: FontWeight.w800,
              letterSpacing: 8,
            ),
            decoration: InputDecoration(
              hintText: '------',
              counterText: '',
              filled: true,
              fillColor: fieldFill,
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: BorderSide.none,
              ),
              focusedBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: const BorderSide(color: primaryBlue, width: 1.5),
              ),
            ),
            onSubmitted: (_) => busy ? null : _verify(),
          ),
          if (_error != null) ...[
            const SizedBox(height: 8),
            Text(_error!, style: const TextStyle(color: Colors.red, fontSize: 12)),
          ],
          if (_info != null) ...[
            const SizedBox(height: 8),
            Text(_info!, style: const TextStyle(color: Colors.green, fontSize: 12)),
          ],
          const SizedBox(height: 8),
          Align(
            alignment: Alignment.centerLeft,
            child: TextButton(
              onPressed: (_cooldown > 0 || busy) ? null : _resend,
              child: Text(
                _cooldown > 0 ? 'Resend code in ${_cooldown}s' : 'Resend code',
                style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w800),
              ),
            ),
          ),
        ],
      ),
      actions: [
        TextButton(
          onPressed: busy ? null : () => Navigator.pop(context, false),
          child: const Text('Cancel'),
        ),
        ElevatedButton(
          onPressed: busy ? null : _verify,
          style: ElevatedButton.styleFrom(
            backgroundColor: primaryBlue,
            foregroundColor: Colors.white,
            elevation: 0,
          ),
          child: _verifying
              ? const SizedBox(
                  width: 16,
                  height: 16,
                  child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2),
                )
              : const Text('Verify'),
        ),
      ],
    );
  }
}
