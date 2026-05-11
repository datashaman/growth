<?php

namespace App\Growth\Guidance;

class PublicGuidanceCatalog
{
    public const SOURCES = [
        'nasa-seh' => [
            'title' => 'NASA Systems Engineering Handbook',
            'publisher' => 'NASA',
            'source_url' => 'https://www.nasa.gov/reference/systems-engineering-handbook/',
            'download_url' => 'https://www.nasa.gov/wp-content/uploads/2018/09/nasa_systems_engineering_handbook_0.pdf',
            'license_status' => 'public_domain_us_government_unless_noted',
            'rule_pack_opportunities' => [
                'requirements lifecycle and validation',
                'technical reviews and lifecycle gates',
                'verification planning',
                'configuration and technical baseline practices',
            ],
        ],
        'nasa-risk' => [
            'title' => 'NASA Risk Management Handbook',
            'publisher' => 'NASA',
            'source_url' => 'https://ntrs.nasa.gov/citations/20240014019',
            'download_url' => 'https://ntrs.nasa.gov/api/citations/20240014019/downloads/SP-20240014019.pdf',
            'license_status' => 'public_use_permitted',
            'rule_pack_opportunities' => [
                'risk statement quality',
                'risk exposure and mitigation checks',
                'residual risk and acceptance prompts',
            ],
        ],
        'nist-ssdf' => [
            'title' => 'NIST SP 800-218 Secure Software Development Framework',
            'publisher' => 'NIST',
            'source_url' => 'https://csrc.nist.gov/pubs/sp/800/218/final',
            'download_url' => 'https://nvlpubs.nist.gov/nistpubs/SpecialPublications/NIST.SP.800-218.pdf',
            'license_status' => 'nist_technical_series_broad_reuse_us_public_domain_for_nist_authored_content',
            'rule_pack_opportunities' => [
                'secure development lifecycle checks',
                'supplier and provenance evidence',
                'release readiness security gates',
            ],
        ],
        'nist-risk' => [
            'title' => 'NIST SP 800-30 Rev. 1 Guide for Conducting Risk Assessments',
            'publisher' => 'NIST',
            'source_url' => 'https://csrc.nist.gov/pubs/sp/800/30/r1/final',
            'download_url' => 'https://nvlpubs.nist.gov/nistpubs/Legacy/SP/nistspecialpublication800-30r1.pdf',
            'license_status' => 'nist_technical_series_broad_reuse_us_public_domain_for_nist_authored_content',
            'rule_pack_opportunities' => [
                'threat and vulnerability framing',
                'likelihood and impact consistency',
                'risk response and residual risk',
            ],
        ],
        'nist-sse' => [
            'title' => 'NIST SP 800-160 Vol. 1 Systems Security Engineering',
            'publisher' => 'NIST',
            'source_url' => 'https://csrc.nist.gov/pubs/sp/800/160/v1/r1/final',
            'download_url' => 'https://nvlpubs.nist.gov/nistpubs/SpecialPublications/NIST.SP.800-160v1r1.pdf',
            'license_status' => 'nist_technical_series_broad_reuse_us_public_domain_for_nist_authored_content',
            'rule_pack_opportunities' => [
                'security requirements quality',
                'secure architecture considerations',
                'assurance evidence and verification planning',
            ],
        ],
    ];

    public static function find(string $id): ?array
    {
        return self::SOURCES[$id] ?? null;
    }
}
