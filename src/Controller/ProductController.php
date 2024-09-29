<?php

namespace App\Controller;

use App\Entity\Product;
use App\Enum\InventoryStatus;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


#[Route('/api/products')]
class ProductController extends AbstractController
{
    private $productRepository;
    private $entityManager;

    public function __construct(ProductRepository $productRepository, EntityManagerInterface $entityManager)
    {
        $this->productRepository = $productRepository;
        $this->entityManager = $entityManager;
    }
    /**
     * Récupérer tous les produits avec pagination et recherche
     * 
     * @OA\Get(
     *     path="/api/products",
     *     @OA\Parameter(name="page", in="query", description="Page number", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="limit", in="query", description="Number of items per page", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="search", in="query", description="Search term", @OA\Schema(type="string")),
     *     @OA\Response(response="200", description="List of products"),
     *     @OA\Response(response="400", description="Bad request")
     * )
    */
    #[Route('', name: 'get_products', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);
        $search = $request->query->get('search', '');

        $offset = ($page - 1) * $limit;

        $queryBuilder = $em->getRepository(Product::class)->createQueryBuilder('p');

        if (!empty($search)) {
            $queryBuilder->where('p.name LIKE :search')
                         ->orWhere('p.code LIKE :search')
                         ->orWhere('p.category LIKE :search')
                         ->setParameter('search', '%' . $search . '%');
        }

        $queryBuilder->orderBy('p.id', 'DESC');

        $totalProducts = count($queryBuilder->getQuery()->getResult());

        
        $queryBuilder->setFirstResult($offset)
                     ->setMaxResults($limit);

        $products = $queryBuilder->getQuery()->getResult();

        
        $productData = array_map(function ($product) {
            return [
                'id' => $product->getId(),
                'code' => $product->getCode(),
                'name' => $product->getName(),
                'description' => $product->getDescription(),
                'image' => $product->getImage(),
                'category' => $product->getCategory(),
                'price' => $product->getPrice(),
                'quantity' => $product->getQuantity(),
                'internalReference' => $product->getInternalReference(),
                'shellId' => $product->getShellId(),
                'inventoryStatus' => $product->getInventoryStatus(),
                'rating' => $product->getRating(),
                'createdAt' => $product->getCreatedAt(),
                'updatedAt' => $product->getUpdatedAt(),
            ];
        }, $products);

        return $this->json([
            'data' => $productData,
            'meta' => [
                'current_page' => $page,
                'items_per_page' => $limit,
                'total_items' => $totalProducts,
                'total_pages' => ceil($totalProducts / $limit),
            ]
        ]);
    }
    /**
     * Récupérer un produit par ID
     * 
     * @OA\Get(
     *     path="/api/products/{id}",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response="200", description="Product details"),
     *     @OA\Response(response="404", description="Product not found")
     * )
    */
    #[Route('/{id}', methods: ['GET'])]
    public function show(int $id): Response
    {
        $product = $this->productRepository->find($id);
        return $product ? $this->json($product) : $this->json(['message' => 'Product not found'], Response::HTTP_NOT_FOUND);
    }
    /**
     * Créer un nouveau produit
     * 
     * @OA\Post(
     *     path="/api/products",
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="image", type="string", format="binary"),
     *                 @OA\Property(property="category", type="string"),
     *                 @OA\Property(property="price", type="number"),
     *                 @OA\Property(property="quantity", type="integer"),
     *                 @OA\Property(property="inventoryStatus", type="string", enum={"INSTOCK", "LOWSTOCK", "OUTOFSTOCK"}),
     *                 @OA\Property(property="shellId", type="integer"),
     *                 @OA\Property(property="rating", type="number")
     *             )
     *         )
     *     ),
     *     @OA\Response(response="201", description="Product created"),
     *     @OA\Response(response="400", description="Invalid input")
     * )
    */
    #[Route('', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $data = $request->request->all();
        $imageFile = $request->files->get('image');

        if (!isset($data['price']) || $data['price'] <= 0) {
            return $this->json([
                'error' => 'Produit non ajouté, Le prix doit être strictement positive.'
            ], Response::HTTP_BAD_REQUEST);
        }
        if (!isset($data['quantity']) || $data['quantity'] < 0) {
            return $this->json([
                'error' => 'Produit non ajouté, La quantité doit être positive.'
            ], Response::HTTP_BAD_REQUEST);
        }
        if (!isset($data['inventoryStatus']) || $this->resolveInventoryStatus($data['inventoryStatus'])[0]==false) {
            return $this->json([
                'error' => 'Valeur absente ou incorrecte spécifiée pour le paramètre inventoryStatus.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $product = new Product();
        if(isset($data['code'])){
            $product->setCode($data['code']);
        }else{
            $product->setCode($this->generateUniqueCode());
        }
        $product->setName($data['name']);
        $product->setDescription(@$data['description'] ?? null);
        if($imageFile){
            if (!$imageFile->isValid()) {
                return $this->json([
                    'error' => 'Le fichier image est invalide.'
                ], Response::HTTP_BAD_REQUEST);
            }
            $uploadDirectory = $this->getParameter('kernel.project_dir') . '/public/uploads';
            if (!is_dir($uploadDirectory)) {
                mkdir($uploadDirectory, 0755, true);
            }
            $newFilename = uniqid() . '.' . $imageFile->guessExtension();
            $imageFile->move($uploadDirectory, $newFilename);
            $imageUrl = '/uploads/' . $newFilename;
            $product->setImage($imageUrl);
        }
        $product->setCategory(@$data['category'] ?? null);
        $product->setPrice($data['price']);
        $product->setQuantity($data['quantity']);
        if(isset($data['internalReference'])){
            $product->setInternalReference($data['internalReference']);
        }else{
            $product->setInternalReference($this->generateNewInternalReference());
        }
        $product->setShellId(@$data['shellId'] ?? 15);
        $product->setInventoryStatus($this->resolveInventoryStatus($data['inventoryStatus'])[1]);
        $product->setRating(@$data['rating'] ?? 0);
        $product->setCreatedAt(1727458500);
        $product->setUpdatedAt(1727458500);
        
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        return $this->json($product, Response::HTTP_CREATED);
    }
    /**
     * Modifier un produit
     * 
     * @OA\Post(
     *     path="/api/products/{id}",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="image", type="string", format="binary"),
     *                 @OA\Property(property="category", type="string"),
     *                 @OA\Property(property="price", type="number"),
     *                 @OA\Property(property="quantity", type="integer"),
     *                 @OA\Property(property="inventoryStatus", type="string", enum={"INSTOCK", "LOWSTOCK", "OUTOFSTOCK"}),
     *                 @OA\Property(property="shellId", type="integer"),
     *                 @OA\Property(property="rating", type="number")
     *             )
     *         )
     *     ),
     *     @OA\Response(response="200", description="Product updated"),
     *     @OA\Response(response="400", description="Invalid input"),
     *     @OA\Response(response="404", description="Product not found")
     * )
    */
    #[Route('/{id}', methods: ['POST'])]
    public function update(Request $request, int $id): Response
    {
        $data = $request->request->all();
        $imageFile = $request->files->get('image');

        $product = $this->productRepository->find($id);
        if (!$product) {
            return $this->json(['message' => 'Product not found'], Response::HTTP_NOT_FOUND);
        }

        if (!isset($data['price']) || $data['price'] <= 0) {
            return $this->json([
                'error' => 'Produit non modifié, Le prix doit être strictement positive.'
            ], Response::HTTP_BAD_REQUEST);
        }
        if (!isset($data['quantity']) || $data['quantity'] < 0) {
            return $this->json([
                'error' => 'Produit non modifié, La quantité doit être positive.'
            ], Response::HTTP_BAD_REQUEST);
        }
        if (!isset($data['inventoryStatus']) || $this->resolveInventoryStatus($data['inventoryStatus'])[0]==false) {
            return $this->json([
                'error' => 'Valeur absente ou incorrecte spécifiée pour le paramètre inventoryStatus.'
            ], Response::HTTP_BAD_REQUEST);
        }

        if(isset($data['code']))
            $product->setCode($data['code']);
        if(isset($data['name']))
            $product->setName($data['name']);
        if(isset($data['description']))
            $product->setDescription($data['description']);
        
        if($imageFile){
            if (!$imageFile->isValid()) {
                return $this->json([
                    'error' => 'Le fichier image est invalide.'
                ], Response::HTTP_BAD_REQUEST);
            }
            $uploadDirectory = $this->getParameter('kernel.project_dir') . '/public/uploads';
            if (!is_dir($uploadDirectory)) {
                mkdir($uploadDirectory, 0755, true);
            }
            $newFilename = uniqid() . '.' . $imageFile->guessExtension();
            $imageFile->move($uploadDirectory, $newFilename);
            $imageUrl = '/uploads/' . $newFilename;
            $product->setImage($imageUrl);
        }
        if(isset($data['category']))
            $product->setCategory($data['category']);
        if(isset($data['price']))
            $product->setPrice($data['price']);
        if(isset($data['quantity']))
            $product->setQuantity($data['quantity']);
        if(isset($data['internalReference']))
            $product->setInternalReference($data['internalReference']);
        if(isset($data['shellId']))
            $product->setShellId($data['shellId']);
        if(isset($data['inventoryStatus']))
            $product->setInventoryStatus($this->resolveInventoryStatus($data['inventoryStatus'])[1]);
        if(isset($data['rating']))
            $product->setRating($data['rating']);

        $product->setUpdatedAt(time());

        $this->entityManager->flush();
        return $this->json($product);
    }
    /**
     * Supprimer un produit
     * 
     * @OA\Delete(
     *     path="/api/products/{id}",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response="200", description="Product deleted"),
     *     @OA\Response(response="404", description="Product not found")
     * )
    */
    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $id): Response
    {
        $product = $this->productRepository->find($id);
        if (!$product) {
            return $this->json(['message' => 'Product not found'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($product);
        $this->entityManager->flush();

        return $this->json(['message' => 'Product deleted successfully']);
    }
    private function resolveInventoryStatus($inventoryStatus){
        if(in_array($inventoryStatus, ["INSTOCK","LOWSTOCK","OUTOFSTOCK"])){
            switch ($inventoryStatus) {
                case 'INSTOCK':
                return [true, InventoryStatus::INSTOCK];
                break;
                case 'LOWSTOCK':
                return [true, InventoryStatus::LOWSTOCK];
                break;
                case 'OUTOFSTOCK':
                return [true, InventoryStatus::OUTOFSTOCK];
                break;
                default:
                return [true, InventoryStatus::INSTOCK];
                break;
            }
        }else{
            return [false, "Wrong InventoryStatus parameter"];
        }
    }
    private function generateNewInternalReference(): string
    {
        do {
            $internalReference = 'REF-' . rand(100, 999) . '-' . rand(100, 999);
            $existingProduct = $this->entityManager->getRepository(Product::class)->findOneBy(['internalReference' => $internalReference]);
        } while ($existingProduct !== null);
    
        return $internalReference;
    }
    private function generateUniqueCode(): string
    {
        do {
            $code = 'P' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
            $existingProduct = $this->entityManager->getRepository(Product::class)->findOneBy(['code' => $code]);
        } while ($existingProduct !== null);
    
        return $code;
    }
}
