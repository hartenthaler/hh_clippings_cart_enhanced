<?php
/*
 * webtrees - clippings cart enhanced
 *
 * based on Vesta module "clippings cart extended"
 *
 * Copyright (C) 2021 Hermann Hartenthaler. All rights reserved.
 * Copyright (C) 2021 Richard Cissée. All rights reserved.
 *
 * webtrees: online genealogy / web based family history software
 * Copyright (C) 2021 webtrees development team.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; If not, see <https://www.gnu.org/licenses/>.
 */

/*
 * tbd
 * ---
 * code: implement deleting of records in cart by type
 * code: when adding descendents or ancestors then allow the specification of the number of generations (like in webtrees 1): 1..max_exist_gen
 * code: check maximum generation level in the tree for a proband and use this in the add menus
 * code: empty cart: show block with "type delete" only if second option is selected
 * code: empty cart: button should be labeled "continue" and not "delete" if third option is selected
 * code: add TRAIT module (?)
 * code: show add options only if they will add new elements to the clippings cart otherwise grey them out
 * code: when adding global sets: instead of using radio buttons use select buttons?
 * translation: translate all new strings to German using po/mo
 * issue: new global add function to add longest descendant-ancestor connection in a tree
 *          (calculate for all individuals in the tree the most distant ancestor,
 *           select the two individuals with the greatest distance,
 *           add all their ancestors and descendants, remove all the leaves)
 * issue: new global add function to add all records of a tree
 * issue: integrate TAM instead of exporting GEDCOM file for external TAM application
 * issue: integrate Lineage
 * issue: use GVExport (GraphViz) code for visualization (?)
 * issue: implement webtrees 1 module "branch export" (starting person and several stop persons/families (stored as profile)
 * issue: new function to add all circles for an individual or a family
 * issue: new action: enhanced list using webtrees standard lists for each type of records
 * idea: use TAM to visualize the hierarchy of location records
 * test: access rights for members and visitors
 * other module - Vesta clippings cart extension: make this module working together with the Vesta module
 * other module - check conflict with JustLight: jc-theme-justlight\resources\views\modules\clippings\show.phtml
 * other module - test with all other themes: Rural, Argon, ...
 * other module - admin/control panel module "unconnected individuals": add button to each group "send to clippings cart"
 * other module - custom modul extended family: send filtered INDI and FAM records to clippings cart
 * other module - search: send search results to clippings cart
 * other module - list of persons with one surname: send them to clippings cart
 */

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\ClippingsCartEnhanced;

use Fisharebest\Webtrees\I18N;
use Aura\Router\Route;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Http\RequestHandlers\IndividualPage;
use Fisharebest\Webtrees\Http\RequestHandlers\FamilyPage;
use Fisharebest\Webtrees\Http\RequestHandlers\MediaPage;
use Fisharebest\Webtrees\Http\RequestHandlers\LocationPage;
use Fisharebest\Webtrees\Http\RequestHandlers\NotePage;
use Fisharebest\Webtrees\Http\RequestHandlers\RepositoryPage;
use Fisharebest\Webtrees\Http\RequestHandlers\SourcePage;
use Fisharebest\Webtrees\Http\RequestHandlers\SubmitterPage;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Media;
use Fisharebest\Webtrees\Location;
use Fisharebest\Webtrees\Module\ModuleMenuTrait;
use Fisharebest\Webtrees\Note;
use Fisharebest\Webtrees\Repository;
use Fisharebest\Webtrees\Source;
use Fisharebest\Webtrees\Submitter;
use Fisharebest\Webtrees\Menu;
use Fisharebest\Webtrees\Module\ClippingsCartModule;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleTabTrait;
use Fisharebest\Webtrees\Module\ModuleMenuInterface;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomExportService;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\View;
use Illuminate\Support\Collection;
use League\Flysystem\FileExistsException;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use RuntimeException;
//use Hartenthaler\Webtrees\Module\ClippingsCartEnhanced\PartnerChainsGlobal;
//use Hartenthaler\Webtrees\Module\ClippingsCartEnhanced\PartnerChains;
//use Hartenthaler\Webtrees\Module\ClippingsCartEnhanced\AncestorCircles;

// control functions
use function assert;
use function app;
use function redirect;
use function route;
use function view;

// string functions
use function is_string;
use function strtolower;
use function addcslashes;
use function preg_match_all;
use function str_replace;

// array functions
use function in_array;
use function count;
use function array_keys;
use function array_key_exists;
use function array_filter;
use function array_map;
use function uasort;
use function array_search;

// file functions
use function fopen;
use function rewind;

/*
require_once(__DIR__ . '/src/AncestorCircles.php');
require_once(__DIR__ . '/src/PartnerChains.php');
require_once(__DIR__ . '/src/PartnerChainsGlobal.php');
*/

class ClippingsCartModuleEnhanced extends ClippingsCartModule
                                  implements ModuleCustomInterface, ModuleMenuInterface
{
    use ModuleMenuTrait;
    use ModuleCustomTrait;

    // List of const for module administration
    public const CUSTOM_TITLE       = 'Clippings cart enhanced';
    public const CUSTOM_DESCRIPTION = 'Add records from your family tree to the clippings cart and execute an action on them.';
    public const CUSTOM_MODULE      = 'hh_clippings_cart_enhanced';
    public const CUSTOM_AUTHOR      = 'Hermann Hartenthaler';
    public const CUSTOM_WEBSITE     = 'https://github.com/hartenthaler/' . self::CUSTOM_MODULE . '/';
    public const CUSTOM_VERSION     = '2.0.17.1';
    public const CUSTOM_LAST        = 'https://github.com/hartenthaler/' .
                                      self::CUSTOM_MODULE. '/raw/main/latest-version.txt';

    // What to add to the cart?
    private const ADD_RECORD_ONLY        = 'add only this record';
    private const ADD_CHILDREN           = 'add children';
    private const ADD_DESCENDANTS        = 'add descendants';
    private const ADD_PARENT_FAMILIES    = 'add parents';
    private const ADD_SPOUSE_FAMILIES    = 'add spouses';
    private const ADD_ANCESTORS          = 'add ancestors';
    private const ADD_ANCESTOR_FAMILIES  = 'add families';
    private const ADD_LINKED_INDIVIDUALS = 'add linked individuals';
    private const ADD_PARTNER_CHAINS     = 'add partner chains for this individual or this family';
    private const ADD_ALL_PARTNER_CHAINS = 'add all partner chains in this tree';
    private const ADD_ALL_CIRCLES        = 'add all circles in this tree';

    // What to execute on records in the clippings cart?
    private const EXECUTE_DOWNLOAD          = 'download records as GEDCOM zip-file (including media files)';
    private const EXECUTE_VISUALIZE         = 'visualize records in a diagram';
    private const EXECUTE_VISUALIZE_TAM     = 'visualize records in a diagram using TAM';
    private const EXECUTE_VISUALIZE_LINEAGE = 'visualize records in a diagram using Lineage';

    // What are the options to delete records in the clippings cart?
    private const EMPTY_ALL      = 'all records';
    private const EMPTY_SET      = 'set of records by type';
    private const EMPTY_SELECTED = 'select records to be deleted';

    // Routes that have a record which can be added to the clipboard
    private const ROUTES_WITH_RECORDS = [
        'Individual' => IndividualPage::class,
        'Family'     => FamilyPage::class,
        'Media'      => MediaPage::class,
        'Location'   => LocationPage::class,
        'Note'       => NotePage::class,
        'Repository' => RepositoryPage::class,
        'Source'     => SourcePage::class,
        'Submitter'  => SubmitterPage::class,
    ];

    // Types of records
    private const TYPES_OF_RECORDS = [
        'Individual' => Individual::class,
        'Family'     => Family::class,
        'Media'      => Media::class,
        'Location'   => Location::class,
        'Note'       => Note::class,
        'Repository' => Repository::class,
        'Source'     => Source::class,
        'Submitter'  => Submitter::class,
    ];

    private const FILENAME_TAM = 'wt2TAM.ged';

    /** @var string */
    private string $exportFilenameTAM;

    /** @var GedcomExportService */
    private $gedcomExportService;

    /** @var int The number of ancestor generations to be added (0 = proband) */
    private int $levelAncestor;

    /** @var int The number of descendant generations to add (0 = proband) */
    private int $levelDescendant;

    public function __construct(
        GedcomExportService $gedcomExportService,
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory)
    {
        $this->gedcomExportService = $gedcomExportService;
        $this->levelAncestor       = PHP_INT_MAX;
        $this->levelDescendant     = PHP_INT_MAX;
        $this->exportFilenameTAM   = self::FILENAME_TAM;
        parent::__construct($gedcomExportService, $responseFactory, $streamFactory);
    }

    /**
     * A menu, to be added to the main application menu.
     *
     * Show:            show records in clippings cart and allow deleting some of them
     * AddRecord:
     *      AddIndividual:   add individual (this record, parents, children, ancestors, descendants, ...)
     *      AddFamily:       add family record
     *      AddMedia:        add media record
     *      AddLocation:     add location record
     *      AddNote:         add shared note record
     *      AddRepository:   add repository record
     *      AddSource:       add source record
     *      AddSubmitter:    add submitter record
     * Global:          add global sets of records (partner chains, circles)
     * Empty:           delete records in clippings cart
     * Execute:         execute an action on records in the clippings cart (export to GEDCOM file, visualize)
     *
     * @param Tree $tree
     *
     * @return Menu|null
     */
    public function getMenu(Tree $tree): ?Menu
    {
        /** @var ServerRequestInterface $request */
        $request = app(ServerRequestInterface::class);

        $route = $request->getAttribute('route');
        assert($route instanceof Route);

        // clippings cart is an array in the session specific for each tree
        $cart  = Session::get('cart', []);

        $submenus = [$this->addMenuClippingsCart($tree, $cart)];

        $action = array_search($route->name, self::ROUTES_WITH_RECORDS, true);
        if ($action !== false) {
            $submenus[] = $this->addMenuAddThisRecord($tree, $route, $action);
        }

        $submenus[] = $this->addMenuAddGlobalRecordSets($tree);

        if (!$this->isCartEmpty($tree)) {
            $submenus[] = $this->addMenuDeleteRecords($tree);
            $submenus[] = $this->addMenuExecuteAction($tree);
        }

        return new Menu($this->title(), '#', 'menu-clippings', ['rel' => 'nofollow'], $submenus);
    }

    /**
     * @param Tree $tree
     * @param array $cart
     *
     * @return Menu
     */
    private function addMenuClippingsCart (Tree $tree, array $cart): Menu
    {
        $count = count($cart[$tree->name()] ?? []);
        $badge = view('components/badge', ['count' => $count]);

        return new Menu(I18N::translate('Records in %s ', $this->title()) . $badge,
            route('module', [
                'module'      => $this->name(),
                'action'      => 'Show',
                'description' => $this->description(),
                'tree'        => $tree->name(),
            ]), 'menu-clippings-cart', ['rel' => 'nofollow']);
    }

    /**
     * @param Tree $tree
     * @param Route $route
     * @param string $action
     *
     * @return Menu
     */
    private function addMenuAddThisRecord (Tree $tree, Route $route, string $action): Menu
    {
        $xref = $route->attributes['xref'];
        assert(is_string($xref));

        return new Menu(I18N::translate('Add this record to the clippings cart'),
            route('module', [
                'module' => $this->name(),
                'action' => 'Add' . $action,
                'xref'   => $xref,
                'tree'   => $tree->name(),
            ]), 'menu-clippings-add', ['rel' => 'nofollow']);
    }

    /**
     * @param Tree $tree
     *
     * @return Menu
     */
    private function addMenuAddGlobalRecordSets (Tree $tree): Menu
    {
        return new Menu(I18N::translate('Add global record sets to the clippings cart'),
            route('module', [
                'module' => $this->name(),
                'action' => 'Global',
                'tree' => $tree->name(),
            ]), 'menu-clippings-add', ['rel' => 'nofollow']);
    }

    /**
     * @param Tree $tree
     *
     * @return Menu
     */
    private function addMenuDeleteRecords (Tree $tree): Menu
    {
        return new Menu(I18N::translate('Delete records in the clippings cart'),
            route('module', [
            'module' => $this->name(),
            'action' => 'Empty',
            'tree'   => $tree->name(),
            ]), 'menu-clippings-empty', ['rel' => 'nofollow']);
    }

    /**
     * @param Tree $tree
     *
     * @return Menu
     */
    private function addMenuExecuteAction (Tree $tree): Menu
    {
        return new Menu(I18N::translate('Execute an action on records in the clippings cart'),
            route('module', [
                'module' => $this->name(),
                'action' => 'Execute',
                'tree' => $tree->name(),
            ]), 'menu-clippings-download', ['rel' => 'nofollow']);
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function getShowAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');
        assert($tree instanceof Tree);

        return $this->viewResponse($this->name() . '::' . 'show', [
            'module'      => $this->name(),
            'records'     => $this->getRecordsInCart($tree),
            'title'       => I18N::translate('Family tree clippings cart'),
            'description' => $this->description(),
            'tree'        => $tree,
        ]);
    }

    /**
     * tbd: show options only if they will add new elements to the clippings cart otherwise grey them out
     * tbd: indicate the number of records which will be added by a button
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function getAddIndividualAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');
        assert($tree instanceof Tree);

        $xref = $request->getQueryParams()['xref'] ?? '';

        $individual = Registry::individualFactory()->make($xref, $tree);
        $individual = Auth::checkIndividualAccess($individual);
        $name       = $individual->fullName();

        $generations['A'] = $this->countAncestorGenerations($individual);
        $generations['D'] = $this->countDescendantGenerations($individual);

        if ($individual->sex() === 'F') {
            $options = [
                self::ADD_RECORD_ONLY       => $name,
                self::ADD_PARENT_FAMILIES   => I18N::translate('%s, her parents and siblings', $name),
                self::ADD_SPOUSE_FAMILIES   => I18N::translate('%s, her spouses and children', $name),
                self::ADD_ANCESTORS         => I18N::translate('%1$s and her ancestors (up to %2$s generations)', $name, $generations['A']),
                self::ADD_ANCESTOR_FAMILIES => I18N::translate('%1$s, her ancestors and their families (up to %2$s generations)', $name, $generations['A']),
                self::ADD_DESCENDANTS       => I18N::translate('%1$s, her spouses and descendants (up to %2$s generations)', $name, $generations['D']),
            ];
        } else {
            $options = [
                self::ADD_RECORD_ONLY       => $name,
                self::ADD_PARENT_FAMILIES   => I18N::translate('%s, his parents and siblings', $name),
                self::ADD_SPOUSE_FAMILIES   => I18N::translate('%s, his spouses and children', $name),
                self::ADD_ANCESTORS         => I18N::translate('%1$s and his ancestors (up to %2$s generations)', $name, $generations['A']),
                self::ADD_ANCESTOR_FAMILIES => I18N::translate('%1$s, his ancestors and their families (up to %2$s generations)', $name, $generations['A']),
                self::ADD_DESCENDANTS       => I18N::translate('%1$s, his spouses and descendants (up to %2$s generations)', $name, $generations['D']),
            ];
        }
        $options[self::ADD_PARTNER_CHAINS] = I18N::translate('the partner chains %s belongs to', $name);

        $title = I18N::translate('Add %s to the clippings cart', $name);

        return $this->viewResponse($this->name() . '::' . 'add-options', [
            'options'     => $options,
            'record'      => $individual,
            'generations' => $generations,
            'title'       => $title,
            'tree'        => $tree,
        ]);
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postAddIndividualAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');
        assert($tree instanceof Tree);

        $params = (array) $request->getParsedBody();

        $xref        = $params['xref'] ?? '';
        $option      = $params['option'] ?? '';
        $generations = $params['generations'] ?? '';
        if ($generations !== '') {
            // tbd set $this->level...
        }

        $individual = Registry::individualFactory()->make($xref, $tree);
        $individual = Auth::checkIndividualAccess($individual);

        switch ($option) {
            case self::ADD_RECORD_ONLY:
                $this->addIndividualToCart($individual);
                break;

            case self::ADD_PARENT_FAMILIES:
                foreach ($individual->childFamilies() as $family) {
                    $this->addFamilyAndChildrenToCart($family);
                }
                break;

            case self::ADD_SPOUSE_FAMILIES:
                foreach ($individual->spouseFamilies() as $family) {
                    $this->addFamilyAndChildrenToCart($family);
                }
                break;

            case self::ADD_ANCESTORS:
                $this->addAncestorsToCart($individual, $this->levelAncestor);
                break;

            case self::ADD_ANCESTOR_FAMILIES:
                $this->addAncestorFamiliesToCart($individual, $this->levelAncestor);
                break;

            case self::ADD_DESCENDANTS:
                foreach ($individual->spouseFamilies() as $family) {
                    $this->addFamilyAndDescendantsToCart($family, $this->levelDescendant);
                }
                break;

            case self::ADD_PARTNER_CHAINS:
                $this->addPartnerChainsToCartIndividual($individual, $individual->spouseFamilies()[0]);
                break;
        }

        return redirect($individual->url());
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function getAddFamilyAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');
        assert($tree instanceof Tree);

        $xref = $request->getQueryParams()['xref'] ?? '';

        $family = Registry::familyFactory()->make($xref, $tree);
        $family = Auth::checkFamilyAccess($family);
        $name   = $family->fullName();

        $options = [
            self::ADD_RECORD_ONLY => $name,
            /* I18N: %s is a family (husband + wife) */
            self::ADD_CHILDREN    => I18N::translate('%s and their children', $name),
            /* I18N: %s is a family (husband + wife) */
            self::ADD_DESCENDANTS => I18N::translate('%s and their descendants', $name),
            /* I18N: %s is a family (husband + wife) */
            self::ADD_PARTNER_CHAINS => I18N::translate('%s and the partner chains they belong to', $name),
        ];

        /* I18N: %s is a family (husband + wife) */
        $title = I18N::translate('Add %s to the clippings cart', $name);

        return $this->viewResponse($this->name() . '::' . 'add-options', [
            'options' => $options,
            'record'  => $family,
            'title'   => $title,
            'tree'    => $tree,
        ]);
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postAddFamilyAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');
        assert($tree instanceof Tree);

        $params = (array) $request->getParsedBody();

        $xref   = $params['xref'] ?? '';
        $option = $params['option'] ?? '';

        $family = Registry::familyFactory()->make($xref, $tree);
        $family = Auth::checkFamilyAccess($family);

        switch ($option) {
            case self::ADD_RECORD_ONLY:
                $this->addFamilyToCart($family);
                break;

            case self::ADD_CHILDREN:
                $this->addFamilyAndChildrenToCart($family);
                break;

            case self::ADD_DESCENDANTS:
                $this->addFamilyAndDescendantsToCart($family);
                break;

            case self::ADD_PARTNER_CHAINS:
                $this->addPartnerChainsToCartFamily($family);
                break;
        }

        return redirect($family->url());
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function getGlobalAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');
        assert($tree instanceof Tree);

        $options[self::ADD_ALL_PARTNER_CHAINS] = I18N::translate('all partner chains in this tree');
        $options[self::ADD_ALL_CIRCLES]        = I18N::translate('all circles of individuals in this tree');

        $title = I18N::translate('Add global record sets to the clippings cart');
        $label = I18N::translate('Add to the clippings cart');

        return $this->viewResponse($this->name() . '::' . 'global', [
            'options' => $options,
            'title'   => $title,
            'label'   => $label,
            'tree'    => $tree,
        ]);
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postGlobalAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');
        assert($tree instanceof Tree);

        $params = (array) $request->getParsedBody();
        $option = $params['option'] ?? '';

        switch ($option) {
            case self::ADD_ALL_PARTNER_CHAINS:
                $this->addPartnerChainsGlobalToCart($tree);
                break;

            default;
            case self::ADD_ALL_CIRCLES:
                $this->addAllCirclesToCart($tree);
                break;
        }

        $url = route('module', [
            'module'      => $this->name(),
            'action'      => 'Show',
            'description' => $this->description(),
            'tree'        => $tree->name(),
        ]);

        return redirect($url);
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function getExecuteAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');
        assert($tree instanceof Tree);

        $options[self::EXECUTE_DOWNLOAD]  = I18N::translate('download records as GEDCOM zip-file (including media files)');
        $options[self::EXECUTE_VISUALIZE] = I18N::translate('visualize records in a diagram');

        $title = I18N::translate('Execute an action on records in the clippings cart');
        $label = I18N::translate('Select an action');

        return $this->viewResponse($this->name() . '::' . 'execute', [
            'options' => $options,
            'title'   => $title,
            'label'   => $label,
            'tree'    => $tree,
        ]);
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postExecuteAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');
        assert($tree instanceof Tree);

        $params = (array) $request->getParsedBody();
        $option = $params['option'] ?? '';

        switch ($option) {
            case self::EXECUTE_DOWNLOAD:
                $url = route('module', [
                    'module' => $this->name(),
                    'action' => 'DownloadForm',
                    'tree'   => $tree->name(),
                ]);
                break;

            default;
            case self::EXECUTE_VISUALIZE:
                $url = route('module', [
                    'module' => $this->name(),
                    'action' => 'Visualize',
                    'tree'   => $tree->name(),
                ]);
                break;
        }

        return redirect($url);
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function getVisualizeAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');
        assert($tree instanceof Tree);

        $options[self::EXECUTE_VISUALIZE_TAM]     = I18N::translate('visualize using TAM');
        $options[self::EXECUTE_VISUALIZE_LINEAGE] = I18N::translate('visualize using lineage');

        $title = I18N::translate('Visualize records in the clippings cart');

        return $this->viewResponse($this->name() . '::' . 'visualize', [
            'options' => $options,
            'title'   => $title,
            'tree'    => $tree,
        ]);
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postVisualizeAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');
        assert($tree instanceof Tree);

        $params = (array) $request->getParsedBody();
        $option = $params['option'] ?? '';

        switch ($option) {
            case self::EXECUTE_VISUALIZE_TAM:
                $url = route('module', [
                    'module' => $this->name(),
                    'action' => 'VisualizeTAM',
                    'tree'   => $tree->name(),
                ]);
                break;

            default;
            case self::EXECUTE_VISUALIZE_LINEAGE:
                $url = route('module', [
                    'module'        => $this->name(),
                    'action'        => 'Show',
                    'description'   => $this->description(),
                    'tree'          => $tree->name(),
                ]);
                break;
        }

        return redirect($url);
    }

    /**
     * delete all records in the clippings cart or delete a set grouped by type of records
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function getEmptyAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');
        assert($tree instanceof Tree);

        $title = I18N::translate('Delete all records, a set of records of the same type, or selected records');
        $label = I18N::translate('Delete');
        $labelType = I18N::translate('Delete all records of a type');
        $recordTypes = $this->countRecordTypesInCart($tree, self::TYPES_OF_RECORDS);
        $plural = self::EMPTY_ALL . ' ' . $badge = view('components/badge', ['count' => $recordTypes['all']]);
        $options[self::EMPTY_ALL] = I18N::plural('this record', $plural, $recordTypes['all']);
        unset($recordTypes['all']);

        if (count($recordTypes) > 1) {
            $recordTypesList = implode(', ', array_keys($recordTypes));
            $options[self::EMPTY_SET] = I18N::translate(self::EMPTY_SET) . ': ' . $recordTypesList;
            $options[self::EMPTY_SELECTED] = I18N::translate(self::EMPTY_SELECTED);
        } else {
            $headingTypes = '';
        }
        $selectedTypes = [];

        return $this->viewResponse($this->name() . '::' . 'empty', [
            'options'        => $options,
            'title'          => $title,
            'label'          => $label,
            'labelType'      => $labelType,
            'recordTypes'    => $recordTypes,
            'selectedTypes'  => $selectedTypes,
            'tree'           => $tree,
        ]);
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postEmptyAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');
        assert($tree instanceof Tree);

        $params = (array) $request->getParsedBody();
        $option = $params['option'] ?? '';

        switch ($option) {
            case self::EMPTY_ALL:
                $cart = Session::get('cart', []);
                $cart[$tree->name()] = [];
                Session::put('cart', $cart);
                $url = route('module', [
                    'module'      => $this->name(),
                    'action'      => 'Show',
                    'description' => $this->description(),
                    'tree'        => $tree->name(),
                ]);
                break;

            case self::EMPTY_SET:
                // tbd
                $url = route('module', [
                    'module'      => $this->name(),
                    'action'      => 'Show',
                    'description' => $this->description(),
                    'tree'        => $tree->name(),
                ]);
                break;

            default;
            case self::EMPTY_SELECTED:
                $url = route('module', [
                    'module'      => $this->name(),
                    'action'      => 'Show',
                    'description' => $this->description(),
                    'tree'        => $tree->name(),
                ]);
                break;
        }

        return redirect($url);
    }

    /**
     * delete one record from the clippings cart
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postRemoveAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');
        assert($tree instanceof Tree);

        $xref = $request->getQueryParams()['xref'] ?? '';

        $cart = Session::get('cart', []);
        unset($cart[$tree->name()][$xref]);
        Session::put('cart', $cart);

        $url = route('module', [
            'module'      => $this->name(),
            'action'      => 'Show',
            'description' => $this->description(),
            'tree'        => $tree->name(),
        ]);

        return redirect($url);
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function getVisualizeTAMAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');
        assert($tree instanceof Tree);

        $user  = $request->getAttribute('user');
        $title = I18N::translate('Visualizing using node-link diagram and TAM');
        $description = I18N::translate('Download %s and import it in TAM application', $this->exportFilenameTAM);
        $label = I18N::translate('Apply privacy settings');

        return $this->viewResponse($this->name() . '::' . 'tam', [
            'is_manager'    => Auth::isManager($tree, $user),
            'is_member'     => Auth::isMember($tree, $user),
            'module'        => $this->name(),
            'title'         => $title,
            'description'   => $description,
            'label'         => $label,
            'tree'          => $tree,
        ]);
    }

    /**
     * visualize using node-link diagram and TAM
     * tbd: instead of writing a GEDCOM file: generate an internal data structure (JSON?) and integrate TAM instead of using an external application
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     *
     */
    public function postVisualizeTAMAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');
        assert($tree instanceof Tree);

        $params = (array) $request->getParsedBody();
        $accessLevel = $this->getAccessLevel($params, $tree);

        $xrefs = $this->getXrefsInCart($tree);
        // keep only XREFs used by Individual or Family records
        foreach ($xrefs as $index => $xref) {
            $object = Registry::gedcomRecordFactory()->make($xref, $tree);
            if (!($object instanceof Individual) && !($object instanceof Family)) {
                unset ($xrefs[$index]);
            }
        }
        $records = $this->getRecordsForExport($tree, $xrefs, $accessLevel);

        $download_filename = $this->exportFilenameTAM;
        // Force a ".ged" suffix
        if (strtolower(pathinfo($download_filename, PATHINFO_EXTENSION)) !== 'ged') {
            $download_filename .= '.ged';
        }

        $stream = fopen('php://temp', 'wb+');
        if ($stream === false) {
            throw new RuntimeException('Failed to create temporary stream');
        }

        // Media file prefix
        $path = $tree->getPreference('MEDIA_DIRECTORY');

        // We have already applied privacy filtering, so do not do it again.
        $encoding = 'UTF-8';
        $this->gedcomExportService->export($tree, true, $encoding, Auth::PRIV_HIDE, $path, $records);
        rewind($stream);

        // Use a stream, so that we do not have to load the entire file into memory.
        $http_stream = app(StreamFactoryInterface::class)->createStreamFromResource($stream);

        /** @var ResponseFactoryInterface $response_factory */
        $response_factory = app(ResponseFactoryInterface::class);

        return $response_factory->createResponse()
            ->withBody($http_stream)
            ->withHeader('Content-Type', 'text/x-gedcom; charset=' . $encoding)
            ->withHeader('Content-Disposition', 'attachment; filename="' . addcslashes($download_filename, '"') . '"');
    }

    /**
     * @param Tree $tree
     *
     * @return bool
     */
    private function isCartEmpty(Tree $tree): bool
    {
        $cart     = Session::get('cart', []);
        $contents = $cart[$tree->name()] ?? [];

        return $contents === [];
    }

    /**
     * get access level based on selected option and user level
     *
     * @param array $params
     * @param Tree $tree
     * @return int
     */
    private function getAccessLevel(array $params, Tree $tree): int
    {
        $privatizeExport = $params['privatize_export'] ?? 'none';

        if ($privatizeExport === 'none' && !Auth::isManager($tree)) {
            $privatizeExport = 'member';
        } elseif ($privatizeExport === 'gedadmin' && !Auth::isManager($tree)) {
            $privatizeExport = 'member';
        } elseif ($privatizeExport === 'user' && !Auth::isMember($tree)) {
            $privatizeExport = 'visitor';
        }

        switch ($privatizeExport) {
            case 'gedadmin':
                return Auth::PRIV_NONE;
            case 'user':
                return Auth::PRIV_USER;
            case 'visitor':
                return Auth::PRIV_PRIVATE;
            case 'none':
            default:
                return Auth::PRIV_HIDE;
        }
    }

    /**
     * add all members of partner chains in a tree to the clippings cart (spouses or partners of partners)
     *
     * @param Tree $tree
     */
    protected function addPartnerChainsGlobalToCart(Tree $tree): void
    {
        $partnerChains = new PartnerChainsGlobal($tree, ['HUSB', 'WIFE']);
        // ignore all standard families (chains have at least 3 partners)
        foreach ($partnerChains->getFamilyCount() as $family => $count) {
            if ($count > 2) {
                $familyObject = Registry::familyFactory()->make($family, $tree);
                if ($familyObject instanceof Family && $familyObject->canShow()) {
                    $this->addFamilyToCart($familyObject);
                }
            }
        }
    }

    /**
     * @param Individual $indi
     * @param Family $family
     * @return void
     */
    protected function addPartnerChainsToCartIndividual(Individual $indi, Family $family): void
    {
        $partnerChains = new PartnerChains($indi, $family);
        $root = $partnerChains->getChainRootNode();

        if ($partnerChains->countPartnerChains($root->chains) > 0) {
            $this->addIndividualToCart($root->indi);
            $this->addFamilyToCart($root->fam);
            foreach ($root->chains as $chain) {
                $this->addPartnerChainsToCartRecursive($chain);
            }
        }
    }

    /**
     * @param Family $family
     * @return void
     */
    protected function addPartnerChainsToCartFamily(Family $family): void
    {
        if ($family->husband() instanceof Individual) {
            $this->addPartnerChainsToCartIndividual($family->husband(), $family);
        } elseif ($family->wife() instanceof Individual) {
            $this->addPartnerChainsToCartIndividual($family->wife(), $family);
        }
    }

    /**
     * @param object $node partner chain node
     * @return void
     */
    protected function addPartnerChainsToCartRecursive(object $node): void
    {
        if ($node->indi instanceof Individual) {
            $this->addIndividualToCart($node->indi);
            $this->addFamilyToCart($node->fam);
            foreach ($node->chains as $chain) {
                $this->addPartnerChainsToCartRecursive($chain);
            }
        }
    }

    /**
     * add all circles (closed loops) of individuals in a tree to the clippings cart
     * by adding individuals and their families without spouses to the clippings cart
     *
     * @param Tree $tree
     */
    protected function addAllCirclesToCart(Tree $tree): void
    {
        $circles = new AncestorCircles($tree, ['FAMS', 'FAMC','ALIA']);
        foreach ($circles->getXrefs() as $xref) {
            $object = Registry::individualFactory()->make($xref, $tree);
            if ($object instanceof Individual) {
                if ($object->canShow()) {
                    $this->addIndividualToCart($object);
                }
            } else {
                $object = Registry::familyFactory()->make($xref, $tree);
                if ($object instanceof Family && $object->canShow()) {
                    $this->addFamilyWithoutSpousesToCart($object);
                }
            }
        }
    }

    /**
     * @param Family $family
     */
    protected function addFamilyToCart(Family $family): void
    {
        foreach ($family->spouses() as $spouse) {
            $this->addIndividualToCart($spouse);
        }
        $this->addFamilyWithoutSpousesToCart($family);
        $this->addFamilyOtherRecordsToCart($family);
    }

    /**
     * @param Family $family
     */
    protected function addFamilyWithoutSpousesToCart(Family $family): void
    {
        $cart = Session::get('cart', []);
        $tree = $family->tree()->name();
        $xref = $family->xref();

        if (($cart[$tree][$xref] ?? false) === false) {
            $cart[$tree][$xref] = true;
            Session::put('cart', $cart);
        }
    }

    /**
     * @param Family $family
     */
    protected function addFamilyOtherRecordsToCart(Family $family): void
    {
        $this->addMediaLinksToCart($family);
        $this->addLocationLinksToCart($family);
        $this->addNoteLinksToCart($family);
        $this->addSourceLinksToCart($family);
        $this->addSubmitterLinksToCart($family);
    }

    /**
     * Count the records of each type in the clippings cart.
     *
     * @param Tree $tree
     * @param array $recordTypes
     *
     * @return int[]
     */
    private function countRecordTypesInCart(Tree $tree, array $recordTypes): array
    {
        $records = $this->getRecordsInCart($tree);
        $recordTypesCount = [];                  // type => count
        $recordTypesCount['all'] = count($records);
        foreach ($recordTypes as $key => $class) {
            foreach ($records as $record) {
                if ($record instanceof $class) {
                    if (array_key_exists($key, $recordTypesCount)) {
                        $recordTypesCount[$key]++;
                    } else {
                        $recordTypesCount[$key] = 1;
                    }
                }
            }
        }
        return $recordTypesCount;
    }

    /**
     * Get the Xrefs in the clippings cart.
     *
     * @param Tree $tree
     *
     * @return array
     */
    private function getXrefsInCart(Tree $tree): array
    {
        $cart = Session::get('cart', []);
        $xrefs = array_keys($cart[$tree->name()] ?? []);
        $xrefs = array_map('strval', $xrefs);           // PHP converts numeric keys to integers.
        return $xrefs;
    }

    /**
     * Get the records in the clippings cart.
     *
     * @param Tree $tree
     *
     * @return array
     */
    private function getRecordsInCart(Tree $tree): array
    {
        $xrefs = $this->getXrefsInCart($tree);
        $records = array_map(static function (string $xref) use ($tree): ?GedcomRecord {
            return Registry::gedcomRecordFactory()->make($xref, $tree);
        }, $xrefs);

        // some records may have been deleted after they were added to the cart, remove them
        $records = array_filter($records);

        // group and sort the records
        uasort($records, static function (GedcomRecord $x, GedcomRecord $y): int {
            return $x->tag() <=> $y->tag() ?: GedcomRecord::nameComparator()($x, $y);
        });

        return $records;
    }

    /**
     * get GEDCOM records from array with XREFs ready to write them to a file
     * and export media files to zip file
     *
     * @param Tree $tree
     * @param array $xrefs
     * @param int $access_level
     * @param Filesystem|null $zip_filesystem
     * @param FilesystemInterface|null $media_filesystem
     *
     * @return Collection
     * @throws FileExistsException
     * @throws FileNotFoundException
     */
    private function getRecordsForExport(Tree $tree, array $xrefs, int $access_level, Filesystem $zip_filesystem = null, FilesystemInterface $media_filesystem = null): Collection
    {
        $records = new Collection();
        foreach ($xrefs as $xref) {
            $object = Registry::gedcomRecordFactory()->make($xref, $tree);
            // The object may have been deleted since we added it to the cart ...
            if ($object instanceof GedcomRecord) {
                $record = $object->privatizeGedcom($access_level);
                $record = $this->removeLinksToUnusedObjects($record, $xrefs);
                $records->add($record);

                if (($zip_filesystem !== null) && ($media_filesystem !== null) && ($object instanceof Media)) {
                    $this->addMediaFilesToArchive($tree, $object, $zip_filesystem, $media_filesystem);
                }
            }
        }
        return $records;
    }

    /**
     * remove links to objects that aren't in the cart
     *
     * @param string $record
     * @param array $xrefs
     *
     * @return string
     */
    private function removeLinksToUnusedObjects(string $record, array $xrefs): string
    {
        preg_match_all('/\n1 ' . Gedcom::REGEX_TAG . ' @(' . Gedcom::REGEX_XREF . ')@(\n[2-9].*)*/', $record, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            if (!in_array($match[1], $xrefs, true)) {
                $record = str_replace($match[0], '', $record);
            }
        }
        preg_match_all('/\n2 ' . Gedcom::REGEX_TAG . ' @(' . Gedcom::REGEX_XREF . ')@(\n[3-9].*)*/', $record, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            if (!in_array($match[1], $xrefs, true)) {
                $record = str_replace($match[0], '', $record);
            }
        }
        preg_match_all('/\n3 ' . Gedcom::REGEX_TAG . ' @(' . Gedcom::REGEX_XREF . ')@(\n[4-9].*)*/', $record, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            if (!in_array($match[1], $xrefs, true)) {
                $record = str_replace($match[0], '', $record);
            }
        }
        return $record;
    }

    /**
     * Add the media files to the zip archive.
     *
     * @param Tree $tree
     * @param Media $object
     * @param Filesystem $zip_filesystem
     * @param FilesystemInterface|null $media_filesystem
     *
     * @throws FileExistsException
     * @throws FileNotFoundException
     */
    private function addMediaFilesToArchive(Tree $tree, Media $object, Filesystem $zip_filesystem, FilesystemInterface $media_filesystem = null): void
    {
        $path = $tree->getPreference('MEDIA_DIRECTORY');        // media file prefix
        foreach ($object->mediaFiles() as $media_file) {
            $from = $media_file->filename();
            $to = $path . $media_file->filename();
            if (!$media_file->isExternal() && $media_filesystem->has($from) && !$zip_filesystem->has($to)) {
                $zip_filesystem->writeStream($to, $media_filesystem->readStream($from));
            }
        }
    }

    /**
     * Recursive function to traverse the tree and add the ancestors
     *
     * @param Individual $individual
     * @param int $level
     *
     * @return void
     */
    protected function addAncestorsToCart(Individual $individual, int $level = PHP_INT_MAX): void
    {
        $this->addIndividualToCart($individual);

        foreach ($individual->childFamilies() as $family) {
            $this->addFamilyToCart($family);

            foreach ($family->spouses() as $parent) {
                if ($level > 0) {
                    $this->addAncestorsToCart($parent, $level - 1);
                }
            }
        }
    }

    /**
     * Recursive function to traverse the tree and add the ancestors and their families
     *
     * @param Individual $individual
     * @param int $level
     *
     * @return void
     */
    protected function addAncestorFamiliesToCart(Individual $individual, int $level = PHP_INT_MAX): void
    {
        foreach ($individual->childFamilies() as $family) {
            $this->addFamilyAndChildrenToCart($family);

            foreach ($family->spouses() as $parent) {
                if ($level > 0) {
                    $this->addAncestorFamiliesToCart($parent, $level - 1);
                }
            }
        }
    }

    /**
     * Recursive function to traverse the tree and add the descendant families
     *
     * @param Family $family
     * @param int $level
     *
     * @return void
     */
    protected function addFamilyAndDescendantsToCart(Family $family, int $level = PHP_INT_MAX): void
    {
        $this->addFamilyAndChildrenToCart($family);

        foreach ($family->children() as $child) {
            foreach ($child->spouseFamilies() as $child_family) {
                if ($level > 0) {
                    $this->addFamilyAndDescendantsToCart($child_family, $level - 1);
                }
            }
        }
    }

    /**
     * Recursive function to traverse the tree and count the maximum ancestor generation
     *
     * @param Individual $individual
     *
     * @return int
     */
    protected function countAncestorGenerations(Individual $individual): int
    {
        $leave = true;
        $countMax = -1;
        foreach ($individual->childFamilies() as $family) {
            foreach ($family->spouses() as $parent) {
                // there are some parent nodes/trees; get the maximum height of parent trees
                $leave = false;
                $countSubtree = $this->countAncestorGenerations($parent);
                if ($countSubtree > $countMax) {
                    $countMax = $countSubtree;
                }
            }
        }
        If ($leave) {
            return 1;               // leave is reached
        } else {
            return $countMax + 1;
        }
    }

    /**
     * Recursive function to traverse the tree and count the maximum descendant generation
     *
     * @param Individual $individual
     *
     * @return int
     */
    protected function countDescendantGenerations(Individual $individual): int
    {
        $leave = true;
        $countMax = -1;
        foreach ($individual->spouseFamilies() as $family) {
            foreach ($family->children() as $child) {
                // there are some child nodes/trees; get the maximum height of child trees
                $leave = false;
                $countSubtree = $this->countDescendantGenerations($child);
                if ($countSubtree > $countMax) {
                    $countMax = $countSubtree;
                }
            }
        }
        If ($leave) {
            return 1;               // leave is reached
        } else {
            return $countMax + 1;
        }
    }

    /**
     * The person or organisation who created this module.
     *
     * @return string
     */
    public function customModuleAuthorName(): string {
        return self::CUSTOM_AUTHOR;
    }

    /**
     * How should this module be identified in the control panel, etc.?
     *
     * @return string
     */
    public function title(): string
    {
        /* I18N: Name of a module */
        return I18N::translate(self::CUSTOM_TITLE);
    }
    /**
     * How should this module be identified in the menu list?
     *
     * @return string
     */
    protected function menuTitle(): string
    {
        return I18N::translate(self::CUSTOM_TITLE);
    }

    /**
     * A sentence describing what this module does.
     *
     * @return string
     */
    public function description(): string
    {
        /* I18N: Description of the module */
        return I18N::translate(self::CUSTOM_DESCRIPTION);
    }

    /**
     * The version of this module.
     *
     * @return string
     */
    public function customModuleVersion(): string
    {
        return self::CUSTOM_VERSION;
    }

    /**
     * A URL that will provide the latest version of this module.
     *
     * @return string
     */
    public function customModuleLatestVersionUrl(): string
    {
        return self::CUSTOM_LAST;
    }

    /**
     * Where to get support for this module?  Perhaps a GitHub repository?
     *
     * @return string
     */
    public function customModuleSupportUrl(): string
    {
        return self::CUSTOM_WEBSITE;
    }

    /**
     * Where does this module store its resources?
     *
     * @return string
     */
    public function resourcesFolder(): string
    {
        return __DIR__ . '/resources/';
    }

    /**
     *  bootstrap
     */
    public function boot(): void
    {
        // Here is also a good place to register any views (templates) used by the module.
        // This command allows the module to use: view($this->name() . '::', 'fish')
        // to access the file ./resources/views/fish.phtml
        View::registerNamespace($this->name(), $this->resourcesFolder() . 'views/');
    }
}
