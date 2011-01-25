<?php
/**
 * EasyPopulate testimonials import
 *
 * @package easypopulate
 * @author johnny <johnny@localmomentum.net>
 * @copyright 2010
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License (2+)
 * @todo <johnny> make it a better class
 */
class EasyPopulateImportReviews extends EasyPopulateProcess
{
	public function run(SplFileInfo $fileInfo)
	{
		$config = $this->config->getValues($this->importHandler);

		$file = $this->openFile($fileInfo);
		if ($file === false) return false;
		
		$em = ZMRuntime::getDatabase()->em;

		foreach ($file as $items) {
			$items = $file->handleRow($items);

			$product = $em->getRepository('Entities\Product')->findOneBy(array('model' => $items['products_model']));
			if (is_null($product)) continue; // @todo error
			$productId = $product->getId();

			// @todo anonymous only for now
			if (!isset($items['author']) || empty($items['author'])) {
				$items['author'] = 'Anonymous';
			}


			$review = $em->getRepository('Entities\Review')->findOneBy(array('author' => $items['author'],  'productId' => $productId));
			
			if (is_null($review)) {
				try {
					$review = new Entities\Review();

					$review->setRating($items['rating']);
					$review->setViewCount($items['read']);
					$review->setAuthor($items['author']);
					$review->setProductId($productId);
					if (isset($items['date_added']) || !empty($items['date_added'])) {
						$review->setDateAdded(new DateTime($items['date_added']));
					}

					$em->persist($review);
					$em->flush();

					$review->setDescription($items['text_1'], 1);

					$em->persist($review);
					$em->flush();

					$output_status = EASYPOPULATE_DISPLAY_RESULT_UPDATE_PRODUCT;
				} catch (Exception $e) {
					$output_status =  EASYPOPULATE_DISPLAY_RESULT_UPDATE_PRODUCT_FAIL;
					continue;
				}
			}

			$output_data = array_values($items);
			// @todo write  status message and status to tempFile 

			$output_data = $this->flattenArray($items);
			if (empty($this->tempFile->filelayout)) {
				$this->tempFile->setFileLayout(array_keys($output_data), true);
			}

			$this->tempFile->write($output_data);

			$this->itemCount++;
		}

		return true;
	}
}
