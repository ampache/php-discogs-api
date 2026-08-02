<?php

declare(strict_types=1);

namespace AmpacheDiscogs\Tests;

use AmpacheDiscogs\Discogs;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Ampache consumes this library from the release branch, so a public signature is a published api.
 * These pin every call src/Plugin/AmpacheDiscogs.php makes, and the version the client reports.
 */
class DiscogsContractTest extends TestCase
{
    /**
     * Each row is one call site in the Ampache plugin: the method and the argument types it passes.
     * Adding an optional parameter after these is safe; making one required, or changing a type, is not.
     *
     * @return list<array{string, list<string>}>
     */
    public static function consumerCallProvider(): array
    {
        return [
            ['search_artist', ['string']],
            ['get_artist', ['int']],
            ['search_album', ['string', 'string']],
            ['search_album', ['string', 'string', 'string']],
            ['get_album', ['int']],
            ['get_album', ['int', 'string']],
        ];
    }

    /**
     * @return list<array{string, int}>
     */
    public static function extendedMethodProvider(): array
    {
        return [
            ['search_artist', 1],
            ['search_album', 3],
            ['get_album', 1],
        ];
    }

    /**
     * @return list<array{string, int}>
     */
    public static function paginatedMethodProvider(): array
    {
        return [
            ['get_artist_releases', 1],
            ['get_label_releases', 1],
            ['get_master_versions', 1],
            ['get_collection_items_by_folder', 2],
            ['get_user_lists', 1],
            ['get_wantlist', 1],
            ['search_album', 3],
            ['search_artist', 1],
            ['search_master', 2],
            ['search_release', 2],
        ];
    }

    /**
     * @param list<string> $passedTypes
     */
    #[DataProvider('consumerCallProvider')]
    public function testAmpacheCanStillMakeEveryCallItMakes(string $method, array $passedTypes): void
    {
        static::assertTrue(method_exists(Discogs::class, $method), $method . ' is called by the Ampache plugin');

        $reflected = new ReflectionMethod(Discogs::class, $method);
        static::assertTrue($reflected->isPublic(), $method . ' must stay public');
        static::assertSame('array', (string) $reflected->getReturnType(), $method . ' must keep returning an array');

        $passed = count($passedTypes);
        static::assertLessThanOrEqual(
            $passed,
            $reflected->getNumberOfRequiredParameters(),
            $method . ' would now demand more arguments than the Ampache plugin passes'
        );

        $parameters = $reflected->getParameters();
        foreach ($passedTypes as $position => $expected) {
            static::assertArrayHasKey($position, $parameters, $method . ' lost parameter ' . $position);
            static::assertSame(
                $expected,
                (string) $parameters[$position]->getType(),
                $method . ' changed the type of parameter ' . $position
            );
        }
    }

    /**
     * Every endpoint Discogs paginates takes a page, defaulting to the first one.
     */
    #[DataProvider('paginatedMethodProvider')]
    public function testPaginatedEndpointsTakeAPage(string $method, int $position): void
    {
        $parameters = (new ReflectionMethod(Discogs::class, $method))->getParameters();

        static::assertArrayHasKey($position, $parameters, $method . ' has no parameter at position ' . $position);

        $page = $parameters[$position];
        static::assertSame('page', $page->getName());
        static::assertInstanceOf(ReflectionNamedType::class, $page->getType());
        static::assertSame('int', $page->getType()->getName());
        static::assertTrue($page->isDefaultValueAvailable());
        static::assertSame(1, $page->getDefaultValue());
    }

    /**
     * Anything added beyond what Ampache passes has to carry a default, or the plugin stops compiling.
     */
    #[DataProvider('extendedMethodProvider')]
    public function testParametersAddedAfterTheConsumerCallsAreOptional(string $method, int $usedByAmpache): void
    {
        $parameters = (new ReflectionMethod(Discogs::class, $method))->getParameters();

        foreach (array_slice($parameters, $usedByAmpache) as $parameter) {
            static::assertTrue(
                $parameter->isDefaultValueAvailable(),
                $method . '::$' . $parameter->getName() . ' was added after the Ampache call sites and needs a default'
            );
        }
    }

    public function testUnpaginatedEndpointsDoNotTakeAPage(): void
    {
        foreach (['get_artist', 'get_label', 'get_list', 'get_profile', 'get_collection_folders'] as $method) {
            $names = array_map(
                static fn(ReflectionParameter $parameter): string => $parameter->getName(),
                (new ReflectionMethod(Discogs::class, $method))->getParameters()
            );

            static::assertNotContains('page', $names, $method . ' returns a single object, Discogs does not page it');
        }
    }

    public function testVersionMatchesTheNewestChangelogEntry(): void
    {
        $changelog = (string) file_get_contents(dirname(__DIR__, 3) . '/CHANGELOG.md');

        static::assertSame(
            1,
            preg_match('/^## (\d+\.\d+\.\d+)$/m', $changelog, $matches),
            'CHANGELOG.md needs a `## X.Y.Z` heading'
        );
        static::assertSame(
            $matches[1] ?? '',
            Discogs::VERSION,
            'Discogs::VERSION is sent as the User-Agent and must match the newest CHANGELOG.md heading'
        );
    }
}
