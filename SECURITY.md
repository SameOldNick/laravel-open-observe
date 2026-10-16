# Security Policy

## Supported Versions

Security updates are provided for actively maintained releases.

| Version                             | Supported                                |
| ----------------------------------- | ---------------------------------------- |
| Latest major (current stable)       | :white_check_mark:                       |
| Previous major                      | :white_check_mark: (critical fixes only) |
| Older majors                        | :x:                                      |
| Pre-releases / development branches | :x:                                      |

If you are unsure whether your version is supported, please report the issue anyway.

## Reporting a Vulnerability

Please do **not** open public GitHub issues for suspected vulnerabilities.

Use one of the following private channels:

1. **Preferred:** GitHub Security Advisories ("Report a vulnerability") for this repository.
2. **Alternative:** Email the maintainer privately with the details.

Include as much information as possible:

- Package version and Laravel/PHP versions
- Affected configuration or feature path
- Proof of concept or reproduction steps
- Impact assessment (what can an attacker do?)
- Any suggested mitigation

## What to Expect

- **Acknowledgement:** within 3 business days
- **Initial triage:** within 7 business days
- **Status updates:** at least every 7 business days while active
- **Fix target:** as quickly as possible based on severity and complexity

If the report is accepted, a patch will be prepared and released in a supported version.

## Coordinated Disclosure

Please allow time for investigation and patching before public disclosure.

When a fix is released, we may publish:

- A security advisory
- A changelog entry
- Upgrade guidance or temporary mitigations

## Scope

This policy covers vulnerabilities in this package's source code and default behavior.

Out of scope (unless they create a direct exploit path in this package):

- Vulnerabilities only in third-party dependencies (report those upstream as well)
- Misconfiguration of the host application
- Issues requiring privileged local access without a package-specific weakness

## Safe Harbor

Good-faith security research and responsible disclosure under this policy are welcome.

Please avoid:

- Accessing or modifying data that does not belong to you
- Disrupting production systems or availability
- Social engineering, phishing, or physical attacks

Thank you for helping keep this package and its users secure.
