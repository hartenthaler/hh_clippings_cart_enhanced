<?php /** @noinspection ALL */
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
 * test: access rights for members and visitors
 * code: make this module working together with the Vesta clippings cart extension module
 * code: search string "allows you to take extracts" and adapt it to new module functions
 * code: show options only if they will add new elements to the clippings cart otherwise grey them out
 * code: when adding descendents or ancestors then allow the specification the number of generations (like in webtrees 1): 1..max_exist_gen
 * code: when adding global sets: instead of using radio buttons use select buttons?
 * code: remove as a menu: delete all records or a group of records (type: all OBJE, all SOUR, ...)
 * code: integrate TAM instead of exporting GEDCOM file
 * code: integrate Lineage
 * code: use GVExport (GraphViz) code for visualization
 * code: implement webtrees 1 module "branch export" (starting person and several stop persons/families (stored as profile)
 * code: new action: enhanced list using webtrees standard list for each type of records
 * translation: translate all new strings to German using po/mo
 * other module - admin module "unconnected individuals": add button to each group "sne dto clippings cart"
 * other module - "extended family": send filtered INDI and FAM records to clippings cart
 * other module - search: send search results to clippings cart
 * other module - list of persons with one surname: send them to clippings cart
 */

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\ClippingsCart;

use Aura\Router\Route;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Http\RequestHandlers\FamilyPage;
use Fisharebest\Webtrees\Http\RequestHandlers\IndividualPage;
use Fisharebest\Webtrees\Http\RequestHandlers\LocationPage;
use Fisharebest\Webtrees\Http\RequestHandlers\MediaPage;
use Fisharebest\Webtrees\Http\RequestHandlers\NotePage;
use Fisharebest\Webtrees\Http\RequestHandlers\RepositoryPage;
use Fisharebest\Webtrees\Http\RequestHandlers\SourcePage;
use Fisharebest\Webtrees\Http\RequestHandlers\SubmitterPage;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Media;
use Fisharebest\Webtrees\Menu;
use Fisharebest\Webtrees\Module\ClippingsCartModule;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleMenuInterface;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomExportService;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\View;
use Illuminate\Support\Collection;
use Illuminate\Database\Capsule\Manager as DB;
use League\Flysystem\FileExistsException;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemInterface;
use League\Flysystem\ZipArchive\ZipArchiveAdapter;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use RuntimeException;

use function redirect;

class ClippingsCartModuleEnhanced extends ClippingsCartModule
                                  implements ModuleCustomInterface, ModuleMenuInterface
{
    use ModuleCustomTrait;

    /**
     * list of const for module administration
     */
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
    private const EXECUTE_DOWNLOAD          = 'download records as GEDCOM zip-file';
    private const EXECUTE_VISUALIZE         = 'visualize records in a diagram';
    private const EXECUTE_VISUALIZE_TAM     = 'visualize records in a diagram using TAM';
    private const EXECUTE_VISUALIZE_LINEAGE = 'visualize records in a diagram using Lineage';

    // What are the options to delete records in the clippings cart?
    private const EMPTY_ALL = 'all %d records';
    private const EMPTY_SET = 'set of records by type:';

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

    public function __construct(GedcomExportService $gedcom_export_service, UserService $user_service)
    {
        parent::__construct($gedcom_export_service, $user_service);
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
     * A sentence describing what this module does.
     *
     * @return string
     */
    public function description(): string
    {
        /* I18N: Description of the “Clippings cart” module */
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
     * Bootstrap the module
     */
    public function onBoot(): void
    {
        View::registerNamespace($this->name(), $this->resourcesFolder() . 'views/');
    }
  
    protected function menuTitle(): string
    {
        return I18N::translate(self::CUSTOM_TITLE);
    }

    /**
     * A menu, to be added to the main application menu.
     *
     * Show:            show records in clippings cart and allow deleting them
     * AddIndividual:   add individual (this record, parents, children, ancestors, descendants, ...)
     * AddFamily:       add family record
     * AddMedia:        add media record
     * AddLocation:     add location record
     * AddNote:         add shared note record
     * AddRepository:   add repository record
     * AddSource:       add source record
     * AddSubmitter:    add submitter record
     * Global:          add global sets of records (partner chains, circles)
     * Empty:           empty clippings cart
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

        $cart  = Session::get('cart', []);
        $count = count($cart[$tree->name()] ?? []);
        $badge = view('components/badge', ['count' => $count]);

        $submenus = [
            new Menu($this->title() . ' ' . $badge, route('module', [
                'module' => $this->name(),
                'action' => 'Show',
                'tree'   => $tree->name(),
            ]), 'menu-clippings-cart', ['rel' => 'nofollow']),
        ];

        $action = array_search($route->name, self::ROUTES_WITH_RECORDS, true);
        if ($action !== false) {
            $xref = $route->attributes['xref'];
            assert(is_string($xref));

            $add_route = route('module', [
                'module' => $this->name(),
                'action' => 'Add' . $action,
                'xref'   => $xref,
                'tree'   => $tree->name(),
            ]);

            $submenus[] = new Menu(I18N::translate('Add this record to the clippings cart'), $add_route, 'menu-clippings-add', ['rel' => 'nofollow']);
        }

        $submenus[] = new Menu(I18N::translate('Add global record sets to the clippings cart'), route('module', [
            'module' => $this->name(),
            'action' => 'Global',
            'tree'   => $tree->name(),
        ]), 'menu-clippings-global', ['rel' => 'nofollow']);

        if (!$this->isCartEmpty($tree)) {
            $submenus[] = new Menu(I18N::translate('Empty the clippings cart'), route('module', [
                'module' => $this->name(),
                'action' => 'Empty',
                'tree'   => $tree->name(),
            ]), 'menu-clippings-empty', ['rel' => 'nofollow']);

            $submenus[] = new Menu(I18N::translate(
                'Execute an action on records in the clipping cart'), route('module', [
                'module' => $this->name(),
                'action' => 'Execute',
                'tree'   => $tree->name(),
            ]), 'menu-clippings-download', ['rel' => 'nofollow']);
        }

        return new Menu($this->title(), '#', 'menu-clippings', ['rel' => 'nofollow'], $submenus);
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
     * tbd: show options only if they will add new elements to the clippings cart otherwise grey them out
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

        if ($individual->sex() === 'F') {
            $options = [
                self::ADD_RECORD_ONLY       => $name,
                self::ADD_PARENT_FAMILIES   => I18N::translate('%s, her parents and siblings', $name),
                self::ADD_SPOUSE_FAMILIES   => I18N::translate('%s, her spouses and children', $name),
                self::ADD_ANCESTORS         => I18N::translate('%s and her ancestors', $name),
                self::ADD_ANCESTOR_FAMILIES => I18N::translate('%s, her ancestors and their families', $name),
                self::ADD_DESCENDANTS       => I18N::translate('%s, her spouses and descendants', $name),
            ];
        } else {
            $options = [
                self::ADD_RECORD_ONLY       => $name,
                self::ADD_PARENT_FAMILIES   => I18N::translate('%s, his parents and siblings', $name),
                self::ADD_SPOUSE_FAMILIES   => I18N::translate('%s, his spouses and children', $name),
                self::ADD_ANCESTORS         => I18N::translate('%s and his ancestors', $name),
                self::ADD_ANCESTOR_FAMILIES => I18N::translate('%s, his ancestors and their families', $name),
                self::ADD_DESCENDANTS       => I18N::translate('%s, his spouses and descendants', $name),
            ];
        }
        $options[self::ADD_PARTNER_CHAINS]     = I18N::translate('the partner chains %s belongs to', $name);

        $title = I18N::translate('Add %s to the clippings cart', $name);

        return $this->viewResponse('modules/clippings/add-options', [
            'options' => $options,
            'record'  => $individual,
            'title'   => $title,
            'tree'    => $tree,
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

        $xref   = $params['xref'] ?? '';
        $option = $params['option'] ?? '';

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
                $this->addAncestorsToCart($individual);
                break;

            case self::ADD_ANCESTOR_FAMILIES:
                $this->addAncestorFamiliesToCart($individual);
                break;

            case self::ADD_DESCENDANTS:
                foreach ($individual->spouseFamilies() as $family) {
                    $this->addFamilyAndDescendantsToCart($family);
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
            self::ADD_PARTNER_CHAINS => I18N::translate('%s and the partner chains they belong to', $name),
        ];

        $title = I18N::translate('Add %s to the clippings cart', $name);

        return $this->viewResponse('modules/clippings/add-options', [
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

        return $this->viewResponse('modules/clippings/global', [
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
    public function postGlobalAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');
        assert($tree instanceof Tree);

        $params = (array) $request->getParsedBody();
        $option = $params['option'] ?? '';

        switch ($option) {
            case self::ADD_ALL_PARTNER_CHAINS:
                $this->addAllPartnerChainsToCart($tree);
                break;

            default;
            case self::ADD_ALL_CIRCLES:
                $this->addAllCirclesToCart($tree);
                break;
        }

        $url = route('module', [
            'module' => $this->name(),
            'action' => 'Show',
            'tree'   => $tree->name(),
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

        $options[self::EXECUTE_DOWNLOAD]  = I18N::translate('download records as GEDCOM zip-file');
        $options[self::EXECUTE_VISUALIZE] = I18N::translate('visualize records in a diagram');

        $title = I18N::translate('Execute an action on records in the clippings cart');

        return $this->viewResponse('modules/clippings/execute', [
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
     * get GEDCOM records from array with XREF ready to write them to a file and export media files
     *
     * @param array $xrefs
     * @param Tree $tree
     * @param int $access_level
     * @param Filesystem $zip_filesystem
     * @param FilesystemInterface|null $media_filesystem
     *
     * @return Collection
     *
     * @throws FileExistsException
     * @throws FileNotFoundException
     */
    private function getRecords(array $xrefs, Tree $tree, int $access_level, Filesystem $zip_filesystem, FilesystemInterface $media_filesystem = null): Collection
    {
        $records = new Collection();
        $path = $tree->getPreference('MEDIA_DIRECTORY');        // media file prefix

        foreach ($xrefs as $xref) {
            $object = Registry::gedcomRecordFactory()->make($xref, $tree);
            // The object may have been deleted since we added it to the cart....
            if ($object instanceof GedcomRecord) {
                $record = $object->privatizeGedcom($access_level);
                // Remove links to objects that aren't in the cart
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

                $records->add($record);

                if (($media_filesystem !== null) && ($object instanceof Media)) {
                    // Add the media files to the archive
                    foreach ($object->mediaFiles() as $media_file) {
                        $from = $media_file->filename();
                        $to = $path . $media_file->filename();
                        if (!$media_file->isExternal() && $media_filesystem->has($from) && !$zip_filesystem->has($to)) {
                            $zip_filesystem->writeStream($to, $media_filesystem->readStream($from));
                        }
                    }
                }
            }
        }
        return $records;
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

        return $this->viewResponse('modules/clippings/visualize', [
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
                    'module' => $this->name(),
                    'action' => 'Show',
                    'tree'   => $tree->name(),
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

        $title = I18N::translate('Delete all records in the clippings cart or a set grouped by type of records');
        $headingSelection = I18N::translate('Delete');
        $recordTypes = $this->countRecordTypesInCart($tree, self::ROUTES_WITH_RECORDS);
        $recordTypes['Test1']=1;
        //$recordTypes['Test2']=2;
        $sum = 0;
        foreach ($recordTypes as $count) {
            $sum += $count;
        }
        $options[self::EMPTY_ALL] = I18N::plural('%d record',self::EMPTY_ALL, $sum, $sum);

        if (count($recordTypes) > 1) {
            $headingTypes = I18N::translate('Delete all records of a type');
            $recordTypesList = implode(', ', array_keys($recordTypes));
            $options[self::EMPTY_SET] = I18N::translate(self::EMPTY_SET) . ' ' . $recordTypesList;
        } else {
            $headingTypes = '';
        }
        $selectedTypes = [];

        return $this->viewResponse('modules/clippings/empty', [
            'options'          => $options,
            'title'            => $title,
            'headingSelection' => $headingSelection,
            'headingTypes'     => $headingTypes,
            'recordTypes'      => $recordTypes,
            'selectedTypes'    => $selectedTypes,
            'tree'             => $tree,
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
                $xref = $request->getQueryParams()['xref'] ?? '';
                $cart = Session::get('cart', []);
                unset($cart[$tree->name()][$xref]);
                Session::put('cart', $cart);
                $url = route('module', [
                    'module' => $this->name(),
                    'action' => 'Show',
                    'tree'   => $tree->name(),
                ]);
                break;

            default;
            case self::EMPTY_SET:
                // tbd
                $url = route('module', [
                    'module' => $this->name(),
                    'action' => 'Show',
                    'tree'   => $tree->name(),
                ]);
                break;
        }

        return redirect($url);
    }

    /**
     * add all members of partner chains in a tree to the clippings cart (spouses and their families)
     *
     * @param Tree $tree
     */
    protected function addAllPartnerChainsToCart(Tree $tree): void
    {
        $links = ['HUSB', 'WIFE'];

        // $rows is array of objects with two pointers:
        // l_from => family XREF and
        // l_to => individual XREF (spouse/partner)
        $rows = DB::table('link')
            ->where('l_file', '=', $tree->id())
            ->whereIn('l_type', $links)
            ->select(['l_from', 'l_to'])
            ->get();

        $indiFamilyList = [];       // individual XREF => array of families XREF
        $familyIndiList = [];       // family XREF     => array of individual XREF (husband and wife)
        foreach ($rows as $row) {
            $indiFamilyList[$row->l_to][] = $row->l_from;
            $familyIndiList[$row->l_from][] = $row->l_to;
        }

        // remove all standard families (chains have at least 3 partners)
        foreach ($familyIndiList as $family => $indiList) {
            if (count($indiList) > 1) {
                $sum = 0;                   // standard: HUSB and WIFE are existing in one family
            } else {
                $sum = 1;                   // only one spouse is existing: we count the not existing spouse, too
            }
            foreach ($indiList as $individual) {
                $countFamilies = count($indiFamilyList[$individual]);
                $sum += $countFamilies;
            }
            if ($sum > 2) {
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
        $chainRootNode = (object)[];
        $chainRootNode->chains = [];
        $chainRootNode->indi = $indi;
        $chainRootNode->fam = $family;

        $stop = (object)[];                                 // avoid endless loops
        $stop->indiList = [];
        $stop->indiList[] = $family->husband()->xref();
        $stop->familyList = [];

        $chains = $this->getPartnerChainsRecursive($chainRootNode, $stop);

        if ($this->countPartnerChains($chains) > 0) {
            $this->addIndividualToCart($chainRootNode->indi);
            $this->addFamilyToCart($chainRootNode->fam);
            foreach ($chains as $chain) {
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
     * get chains of partners recursive
     *
     * @param object $node
     * @param object $stop stoplist with arrays of indi-xref and fam-xref (modified by this function)
     * @return array
     */
    private function getPartnerChainsRecursive(object $node, object &$stop): array
    {
        $new_nodes = [];            // array of object ($node->indi; $node->chains)
        $i = 0;
        foreach ($node->indi->spouseFamilies() as $family) {
            if (!in_array($family->xref(), $stop->familyList)) {
                foreach ($family->spouses() as $spouse) {
                    if ($spouse->xref() !== $node->indi->xref()) {
                        if (!in_array($spouse->xref(), $stop->indiList)) {
                            $new_node = (object)[];
                            $new_node->chains = [];
                            $new_node->indi = $spouse;
                            $new_node->fam = $family;
                            $stop->indiList[] = $spouse->xref();
                            $stop->familyList[] = $family->xref();
                            $new_node->chains = $this->getPartnerChainsRecursive($new_node, $stop);
                            $new_nodes[$i] = $new_node;
                            $i++;
                        } else {
                            break;
                        }
                    }
                }
            }
        }
        return $new_nodes;
    }

    /**
     * count individuals in partner chains
     *
     * @param array of partner chain nodes
     * @return int
     */
    private function countPartnerChains(array $chains): int
    {
        $allcount = 0;
        $counter = 0;
        foreach ($chains as $chain) {
            $this->countPartnerChainsRecursive($chain, $counter);
            $allcount += $counter;
        }
        if ($allcount <= 2) {           // ignore chains with only one couple
            $allcount = 0;
        }
        return $allcount;
    }

    /**
     * count individuals in partner chains recursively
     *
     * @param object $node partner chain node
     * @param int $counter counter for sex of individuals (modified by function)
     */
    private function countPartnerChainsRecursive(object $node, int &$counter)
    {
        if ($node && $node->indi instanceof Individual) {
            $counter++;
            foreach ($node->chains as $chain) {
                $this->countPartnerChainsRecursive($chain, $counter);
            }
        }
    }

    /**
     * add all circles (loops) of individuals in a tree to the clippings cart
     *
     * @param Tree $tree
     */
    protected function addAllCirclesToCart(Tree $tree): void
    {
        $links = ['FAMS', 'FAMC','ALIA'];
        // $rows is an array of objects with two pointers:
        // l_from => family XREF and
        // l_to => individual XREF (spouse/partner)
        $rows = DB::table('link')
            ->where('l_file', '=', $tree->id())
            ->whereIn('l_type', $links)
            ->select(['l_from', 'l_to'])
            ->get();

        $graph = DB::table('individuals')
            ->where('i_file', '=', $tree->id())
            ->pluck('i_id')
            ->mapWithKeys(static function (string $xref): array {
                return [$xref => []];
            })
            ->all();

        foreach ($rows as $row) {
            $graph[$row->l_from][$row->l_to] = 1;
            $graph[$row->l_to][$row->l_from] = 1;
        }
        // $graph is now a square matrix with XREFS of individuals and families in rows and columns
        // value of $graph is 0 or 1 if individual belongs to a family or a family contains an individual

        // eliminate all leaves of the tree as long as there are leaves existing
        $count = count(array_keys($graph));
        do {
            $count2 = $count;
            $this->reduceGraph($graph);
            $count = count(array_keys($graph));
        } while ($count2 > $count);

        // add now individuals and families to the clippings cart based on the remaining XREFs in $graph
        foreach (array_keys($graph) as $xref) {
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
     * eliminate all the leaves in the tree
     *
     * @param array $graph
     */
    protected function reduceGraph(array &$graph)
    {
        foreach ($graph as $column => $array) {
            if (count($graph[$column]) == 0) {                      // not connected
                unset($graph[$column]);
            } elseif (count($graph[$column]) == 1) {                // leave
                foreach ($graph[$column] as $index => $value) {
                    unset($graph[$index][$column]);
                }
                unset($graph[$column]);
            }
        }
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
        $title = I18N::translate('Visualizing using node-link diagram and TAM') . ' — ' . I18N::translate('Download tam.ged and import it in TAM application');

        return $this->viewResponse('modules/clippings/tam', [
            'is_manager' => Auth::isManager($tree, $user),
            'is_member'  => Auth::isMember($tree, $user),
            'module'     => $this->name(),
            'title'      => $title,
            'tree'       => $tree,
        ]);
    }

    /**
     * visualize using TAM - up to now: special export of GEDCOM file
     * tbd: zip file is not necessary - write direct to tam.ged
     * tbd: instead of writing a GEDCOM file: generate an internal data structure (JSON?) and integrate TAM instead of using an external application
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws FileExistsException
     * @throws FileNotFoundException
     */
    public function postVisualizeTAMAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');
        assert($tree instanceof Tree);

        $params = (array) $request->getParsedBody();
        $accessLevel = $this->getAccessLevel($params, $tree);

        $cart = Session::get('cart', []);
        $xrefs = array_keys($cart[$tree->name()] ?? []);
        $xrefs = array_map('strval', $xrefs); // PHP converts numeric keys to integers.

        foreach ($xrefs as $index => $xref) {
            $object = Registry::gedcomRecordFactory()->make($xref, $tree);
            if (!($object instanceof Individual) && !($object instanceof Family)) {
                unset ($xrefs[$index]);
            }
        }

        $temp_zip_file  = stream_get_meta_data(tmpfile())['uri'];
        $zip_adapter    = new ZipArchiveAdapter($temp_zip_file);
        $zip_filesystem = new Filesystem($zip_adapter);

        $records = $this->getRecords($xrefs, $tree, $accessLevel, $zip_filesystem);

        $stream = fopen('php://temp', 'wb+');
        if ($stream === false) {
            throw new RuntimeException('Failed to create temporary stream');
        }

        // We have already applied privacy filtering, so do not do it again.
        $encoding = 'UTF-8';
        //parent::gedcom_export_service->export($tree, $stream, false, $encoding, Auth::PRIV_HIDE, '', $records);
        rewind($stream);

        // Finally, add the GEDCOM file to the .ZIP file.
        $zip_filesystem->writeStream('tam.ged', $stream);

        // Need to force-close ZipArchive filesystems.
        $zip_adapter->getArchive()->close();

        // Use a stream, so that we do not have to load the entire file into memory.
        $stream = app(StreamFactoryInterface::class)->createStreamFromFile($temp_zip_file);

        /** @var ResponseFactoryInterface $response_factory */
        $response_factory = app(ResponseFactoryInterface::class);

        return $response_factory->createResponse()
            ->withBody($stream)
            ->withHeader('Content-Type', 'application/zip')
            ->withHeader('Content-Disposition', 'attachment; filename="clippings.zip');
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
        $cart = Session::get('cart', []);

        $xrefs = array_keys($cart[$tree->name()] ?? []);
        $xrefs = array_map('strval', $xrefs); // PHP converts numeric keys to integers.

        // Fetch all the records in the cart.
        $records = array_map(static function (string $xref) use ($tree): ?GedcomRecord {
            return Registry::gedcomRecordFactory()->make($xref, $tree);
        }, $xrefs);

        // Some records may have been deleted after they were added to the cart.
        $records = array_filter($records);

        // Count types
        $recordTypesCount = [];                  // type => count
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

}
