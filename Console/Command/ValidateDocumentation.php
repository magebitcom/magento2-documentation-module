<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Console\Command;

use Magebit\Documentation\Api\PathResolverInterface;
use Magebit\Documentation\Model\Config\Converter;
use Magebit\Documentation\Model\Config\Data as ConfigData;
use Magento\Framework\Acl\AclResource\ProviderInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Fails the build on a documentation path that does not resolve, or an acl attribute naming a
 * resource acl.xml never declares. Both hide a section without any visible error.
 *
 * @phpstan-import-type DocModule from Converter
 * @phpstan-import-type DocSection from Converter
 */
class ValidateDocumentation extends Command
{
    private const NAME = 'magebit:documentation:validate';

    /**
     * @param ConfigData $config
     * @param PathResolverInterface $pathResolver
     * @param ProviderInterface $aclResourceProvider
     * @param string|null $name
     */
    public function __construct(
        private readonly ConfigData $config,
        private readonly PathResolverInterface $pathResolver,
        private readonly ProviderInterface $aclResourceProvider,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName(self::NAME)
            ->setDescription(
                'Fail if a documentation path does not resolve or an acl attribute names an undeclared resource.'
            );

        parent::configure();
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var array<string, DocModule> $modules */
        $modules = $this->config->get();
        $declared = $this->declaredResourceIds($this->aclResourceProvider->getAclResources());
        $failures = [];

        foreach ($modules as $moduleName => $module) {
            foreach ($module['sections'] as $section) {
                $failures = [...$failures, ...$this->check($moduleName, $section, $declared)];
            }
        }

        if ($failures !== []) {
            $output->writeln('<error>Documentation configuration errors:</error>');

            foreach ($failures as $failure) {
                $output->writeln(' - ' . $failure);
            }

            return Command::FAILURE;
        }

        $output->writeln('<info>OK - every documentation path resolves and every ACL resource exists.</info>');

        return Command::SUCCESS;
    }

    /**
     * Everything wrong with one section, as ready-to-print lines.
     *
     * @param string $moduleName
     * @param DocSection $section
     * @param list<string> $declared
     * @return list<string>
     */
    private function check(string $moduleName, array $section, array $declared): array
    {
        $label = $moduleName . ' / ' . $section['name'];
        $failures = [];

        if (!$this->resolves($moduleName, $section)) {
            $failures[] = sprintf('%s: path "%s" does not resolve.', $label, $section['path']);
        }

        $acl = $section['acl'];

        if ($acl !== null && !in_array($acl, $declared, true)) {
            $failures[] = sprintf('%s: ACL resource "%s" is not declared in acl.xml.', $label, $acl);
        }

        return $failures;
    }

    /**
     * Whether a section points at something that exists, a file for a changelog and a directory otherwise.
     *
     * @param string $moduleName
     * @param DocSection $section
     * @return bool
     */
    private function resolves(string $moduleName, array $section): bool
    {
        if ($section['isChangelog']) {
            return $this->pathResolver->resolveChangelogFile($moduleName, $section['path']) !== null;
        }

        return $this->pathResolver->resolveSectionRoot($moduleName, $section['path']) !== null;
    }

    /**
     * Flatten the acl.xml resource tree into a plain list of resource ids.
     *
     * @param array<array-key,mixed> $resources
     * @return list<string>
     */
    private function declaredResourceIds(array $resources): array
    {
        $ids = [];

        foreach ($resources as $resource) {
            if (!is_array($resource)) {
                continue;
            }

            if (isset($resource['id']) && is_string($resource['id'])) {
                $ids[] = $resource['id'];
            }

            if (isset($resource['children']) && is_array($resource['children'])) {
                $ids = [...$ids, ...$this->declaredResourceIds($resource['children'])];
            }
        }

        return $ids;
    }
}
